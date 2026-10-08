<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\PurchasableType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommercePricingService
{
    public function __construct(private CommerceCatalogService $catalog) {}

    /** @return array{regular_price: string, active_price: string, promotional_savings: string, promotion_active: bool, promotion_ends_at: ?string} */
    public function product(Course|Package $product, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $regular = BigDecimal::of($product->price)->toScale(2, RoundingMode::Unnecessary);
        $active = $product->promotional_price !== null
            && $product->discount_starts_at !== null
            && $product->discount_ends_at !== null
            && $product->discount_starts_at->lt($product->discount_ends_at)
            && BigDecimal::of($product->promotional_price)->compareTo($regular) < 0
            && $at->greaterThanOrEqualTo($product->discount_starts_at)
            && $at->lessThan($product->discount_ends_at);
        $price = $active ? BigDecimal::of($product->promotional_price) : $regular;

        return [
            'regular_price' => (string) $regular,
            'active_price' => (string) $price->toScale(2, RoundingMode::Unnecessary),
            'promotional_savings' => (string) $regular->minus($price)->toScale(2, RoundingMode::Unnecessary),
            'promotion_active' => $active,
            'promotion_ends_at' => $active ? $product->discount_ends_at->toIso8601String() : null,
        ];
    }

    public function normalizeCode(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /** @return array{items: array<int, array<string, mixed>>, subtotal: string, promotional_savings: string, coupon_discount: string, discount_total: string, total: string, currency: ?string, coupon: ?Coupon} */
    public function quote(Collection $cartItems, ?string $code = null, ?User $user = null, bool $lock = false): array
    {
        $pricedAt = now();
        $currency = $this->catalog->currency();
        $lines = [];
        $subtotal = BigDecimal::zero()->toScale(2);
        $promotionalSavings = BigDecimal::zero()->toScale(2);

        foreach ($cartItems as $cartItem) {
            /** @var CartItem $cartItem */
            $product = $lock
                ? $this->catalog->findPurchasable(PurchasableType::from($cartItem->purchasable_type), $cartItem->purchasable_id, true)
                : $cartItem->purchasable;
            if (! $this->catalog->isPurchasable($product)) {
                throw ValidationException::withMessages(['cart' => 'A cart item is no longer available.']);
            }
            $price = $this->product($product, $pricedAt);
            $regular = BigDecimal::of($price['regular_price']);
            $active = BigDecimal::of($price['active_price']);
            $subtotal = $subtotal->plus($regular);
            $promotionalSavings = $promotionalSavings->plus($regular->minus($active));
            $lines[] = ['cart_item' => $cartItem, 'product' => $product, 'regular_price' => $price['regular_price'], 'active_price' => $price['active_price'], 'promotional_discount' => $price['promotional_savings'], 'coupon_discount' => '0.00', 'total' => $price['active_price']];
        }

        $coupon = null;
        $couponDiscount = BigDecimal::zero()->toScale(2);
        if ($code !== null && trim($code) !== '') {
            $coupon = Coupon::query()->where('code', $this->normalizeCode($code))->when($lock, fn ($query) => $query->lockForUpdate())->first();
            if ($coupon === null || ! $coupon->is_active || ($coupon->starts_at !== null && $pricedAt->lt($coupon->starts_at)) || ($coupon->expires_at !== null && $pricedAt->gte($coupon->expires_at))) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon is not currently valid.']);
            }
            if (! in_array($coupon->discount_type, ['fixed', 'percentage'], true)
                || ! in_array($coupon->applies_to, ['all', 'course', 'package'], true)
                || BigDecimal::of($coupon->discount_value)->isLessThanOrEqualTo(0)
                || ($coupon->discount_type === 'percentage' && BigDecimal::of($coupon->discount_value)->isGreaterThan(100))) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon has an invalid configuration.']);
            }
            if ($coupon->usage_limit !== null && $coupon->redemptions()->whereIn('status', ['reserved', 'consumed'])->count() >= $coupon->usage_limit) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon has reached its usage limit.']);
            }
            if ($user !== null && $coupon->per_user_limit !== null && $coupon->redemptions()->where('user_id', $user->id)->whereIn('status', ['reserved', 'consumed'])->count() >= $coupon->per_user_limit) {
                throw ValidationException::withMessages(['coupon_code' => 'You have reached this coupon usage limit.']);
            }
            $payableBeforeCoupon = $subtotal->minus($promotionalSavings);
            if ($coupon->minimum_order_amount !== null && $payableBeforeCoupon->compareTo(BigDecimal::of($coupon->minimum_order_amount)) < 0) {
                throw ValidationException::withMessages(['coupon_code' => 'The order does not meet the coupon minimum.']);
            }
            $eligible = [];
            foreach ($lines as $index => $line) {
                $type = $line['cart_item']->purchasable_type;
                if ($coupon->applies_to === 'all' || ($coupon->applies_to === $type && in_array($line['product']->id, $coupon->product_ids ?? [], true))) {
                    $eligible[] = $index;
                }
            }
            if ($eligible === []) {
                throw ValidationException::withMessages(['coupon_code' => 'This coupon does not apply to the cart.']);
            }
            $remaining = BigDecimal::of($coupon->discount_value);
            foreach ($eligible as $index) {
                $active = BigDecimal::of($lines[$index]['active_price']);
                $discount = $coupon->discount_type === 'percentage'
                    ? $active->multipliedBy($remaining)->dividedBy(100, 2, RoundingMode::HalfUp)
                    : ($remaining->compareTo($active) > 0 ? $active : $remaining);
                if ($discount->compareTo($active) > 0) {
                    $discount = $active;
                }
                $couponDiscount = $couponDiscount->plus($discount);
                $lines[$index]['coupon_discount'] = (string) $discount->toScale(2, RoundingMode::Unnecessary);
                $lines[$index]['total'] = (string) $active->minus($discount)->toScale(2, RoundingMode::Unnecessary);
                if ($coupon->discount_type === 'fixed') {
                    $remaining = $remaining->minus($discount);
                }
            }
        }

        $discountTotal = $promotionalSavings->plus($couponDiscount);
        if ($subtotal->isGreaterThan('9999999999.99')) {
            throw ValidationException::withMessages(['cart' => 'The cart amount exceeds the supported limit.']);
        }

        return ['items' => $lines, 'subtotal' => (string) $subtotal, 'promotional_savings' => (string) $promotionalSavings, 'coupon_discount' => (string) $couponDiscount, 'discount_total' => (string) $discountTotal, 'total' => (string) $subtotal->minus($discountTotal), 'currency' => $currency, 'coupon' => $coupon];
    }
}
