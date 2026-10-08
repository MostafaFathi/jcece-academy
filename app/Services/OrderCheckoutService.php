<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\OrderStatus;
use App\PurchasableType;
use Brick\Math\BigDecimal;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(private CommerceCatalogService $catalog, private CommercePricingService $pricing, private OrderAccessProvisioningService $provisioning, private TransactionalDeliveryService $deliveries) {}

    /**
     * Checkout retries are idempotent per user and UUID. The same key always
     * returns the first committed order and never consumes the cart twice.
     *
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, notes?: ?string, idempotency_key: string, expected_total: string, expected_coupon_code?: ?string}  $attributes
     */
    public function checkout(User $user, array $attributes): Order
    {
        return DB::transaction(function () use ($user, $attributes): Order {
            $existingOrder = Order::query()
                ->whereBelongsTo($user)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->first();

            if ($existingOrder !== null) {
                return $this->loadOrder($existingOrder);
            }

            $cart = Cart::query()->whereBelongsTo($user)->lockForUpdate()->first();

            if ($cart === null) {
                throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
            }

            $existingOrder = Order::query()
                ->whereBelongsTo($user)
                ->where('idempotency_key', $attributes['idempotency_key'])
                ->lockForUpdate()
                ->first();

            if ($existingOrder !== null) {
                return $this->loadOrder($existingOrder);
            }

            $cartItems = $cart->items()
                ->orderBy('purchasable_type')
                ->orderBy('purchasable_id')
                ->lockForUpdate()
                ->get();

            if ($cartItems->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
            }

            $currency = $this->catalog->currency();

            if ($currency === null) {
                throw ValidationException::withMessages([
                    'currency' => 'Checkout currency is not configured with a valid 3-character ISO code.',
                ]);
            }

            $quote = $this->pricing->quote($cartItems, $cart->coupon_code, $user, true);
            if ($quote['currency'] === null) {
                throw ValidationException::withMessages(['currency' => 'Checkout currency is not configured.']);
            }
            $expectedCode = isset($attributes['expected_coupon_code']) ? $this->pricing->normalizeCode($attributes['expected_coupon_code']) : null;
            if ($quote['total'] !== $attributes['expected_total'] || $expectedCode !== $cart->coupon_code) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Cart pricing changed. Refresh the cart and confirm the new total.',
                    'code' => 'pricing_changed',
                    'pricing' => collect($quote)->except(['items', 'coupon'])->all(),
                ], 409));
            }
            $coupon = $quote['coupon'];
            $isFree = $quote['total'] === '0.00';
            $order = $user->orders()->create([
                'order_number' => 'JCEC-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'idempotency_key' => $attributes['idempotency_key'],
                'status' => $isFree ? OrderStatus::Paid : OrderStatus::Pending,
                'currency' => $currency,
                'subtotal' => $quote['subtotal'],
                'discount_total' => $quote['discount_total'],
                'tax_total' => '0.00',
                'total' => $quote['total'],
                'coupon_id' => $coupon?->id,
                'coupon_code_snapshot' => $coupon?->code,
                'coupon_type_snapshot' => $coupon?->discount_type,
                'coupon_value_snapshot' => $coupon?->discount_value,
                'customer_name' => $attributes['customer_name'],
                'customer_email' => $attributes['customer_email'],
                'locale_snapshot' => in_array($attributes['locale'] ?? null, ['ar', 'en'], true) ? $attributes['locale'] : ($user->preferred_locale ?: 'ar'),
                'customer_phone' => $attributes['customer_phone'],
                'notes' => $attributes['notes'] ?? null,
                'placed_at' => now(),
                'paid_at' => $isFree ? now() : null,
            ]);

            foreach ($quote['items'] as $line) {
                $cartItem = $line['cart_item'];
                $type = PurchasableType::from($cartItem->purchasable_type);
                $product = $line['product'];
                $orderItem = $order->items()->create([
                    'purchasable_type' => $type->value,
                    'purchasable_id' => $product->id,
                    'title' => $product->title,
                    'quantity' => 1,
                    'unit_price' => $line['regular_price'],
                    'discount_amount' => (string) BigDecimal::of($line['promotional_discount'])->plus($line['coupon_discount']),
                    'promotional_discount_amount' => $line['promotional_discount'],
                    'coupon_discount_amount' => $line['coupon_discount'],
                    'total' => $line['total'],
                    'access_duration_days' => $product->access_duration_days,
                    'sequential_completion_percentage' => $product instanceof Package && $product->is_sequential
                        ? $product->sequential_completion_percentage
                        : null,
                ]);

                if ($product instanceof Package) {
                    $memberships = $product->courseMemberships()
                        ->with(['course' => fn ($query) => $query->withTrashed()])
                        ->lockForUpdate()
                        ->get();

                    foreach ($memberships as $membership) {
                        $orderItem->packageCourses()->create([
                            'course_id' => $membership->course_id,
                            'course_title' => $membership->course->title,
                            'sort_order' => $membership->sort_order,
                        ]);
                    }
                }
            }

            if ($coupon !== null) {
                $coupon->redemptions()->create(['order_id' => $order->id, 'user_id' => $user->id, 'status' => $isFree ? 'consumed' : 'reserved', 'consumed_at' => $isFree ? now() : null]);
            }

            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            if ($isFree) {
                $this->provisioning->provision($order);
            } else {
                $this->deliveries->recordForOrder('order_created', 'Order', $order->id, $order);
            }

            return $this->loadOrder($order->refresh());
        }, 3);
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load(['items.packageCourses', 'payments']);
    }
}
