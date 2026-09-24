<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Package;
use App\Models\User;
use App\OrderStatus;
use App\PurchasableType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderCheckoutService
{
    public function __construct(private CommerceCatalogService $catalog) {}

    /**
     * Checkout retries are idempotent per user and UUID. The same key always
     * returns the first committed order and never consumes the cart twice.
     *
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, notes?: ?string, idempotency_key: string}  $attributes
     */
    public function checkout(User $user, array $attributes): Order
    {
        return DB::transaction(function () use ($user, $attributes): Order {
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

            $currency = strtoupper((string) config('jcec.commerce.currency'));

            if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
                throw ValidationException::withMessages([
                    'currency' => 'Checkout currency is not configured with a valid 3-character ISO code.',
                ]);
            }

            $products = [];
            $subtotal = BigDecimal::zero()->toScale(2);

            foreach ($cartItems as $cartItem) {
                /** @var CartItem $cartItem */
                $type = PurchasableType::from($cartItem->purchasable_type);
                $product = $this->catalog->findPurchasable($type, $cartItem->purchasable_id, true);
                $unitPrice = BigDecimal::of($product->price)->toScale(2, RoundingMode::Unnecessary);
                $subtotal = $subtotal->plus($unitPrice);
                $products[] = [$type, $product, $unitPrice];
            }

            $subtotalAmount = (string) $subtotal->toScale(2, RoundingMode::Unnecessary);
            $order = $user->orders()->create([
                'order_number' => 'JCEC-'.now()->format('Ymd').'-'.Str::upper((string) Str::ulid()),
                'idempotency_key' => $attributes['idempotency_key'],
                'status' => OrderStatus::Pending,
                'currency' => $currency,
                'subtotal' => $subtotalAmount,
                'discount_total' => '0.00',
                'tax_total' => '0.00',
                'total' => $subtotalAmount,
                'customer_name' => $attributes['customer_name'],
                'customer_email' => $attributes['customer_email'],
                'customer_phone' => $attributes['customer_phone'],
                'notes' => $attributes['notes'] ?? null,
                'placed_at' => now(),
            ]);

            foreach ($products as [$type, $product, $unitPrice]) {
                $orderItem = $order->items()->create([
                    'purchasable_type' => $type->value,
                    'purchasable_id' => $product->id,
                    'title' => $product->title,
                    'quantity' => 1,
                    'unit_price' => (string) $unitPrice,
                    'discount_amount' => '0.00',
                    'total' => (string) $unitPrice,
                    'access_duration_days' => $product->access_duration_days,
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

            $cart->items()->delete();

            return $this->loadOrder($order);
        }, 3);
    }

    private function loadOrder(Order $order): Order
    {
        return $order->load(['items.packageCourses', 'payments']);
    }
}
