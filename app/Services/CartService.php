<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\PurchasableType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private CommerceCatalogService $catalog, private CommercePricingService $pricing) {}

    public function get(User $user): Cart
    {
        $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);

        return $this->loadSummary($cart);
    }

    public function add(User $user, PurchasableType $type, int $purchasableId): Cart
    {
        return DB::transaction(function () use ($user, $type, $purchasableId): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $this->catalog->findPurchasable($type, $purchasableId, true);

            $cart->items()->firstOrCreate([
                'purchasable_type' => $type->value,
                'purchasable_id' => $purchasableId,
            ]);

            return $this->loadSummary($cart);
        });
    }

    public function remove(User $user, int $cartItemId): Cart
    {
        return DB::transaction(function () use ($user, $cartItemId): Cart {
            $cart = Cart::query()->whereBelongsTo($user)->lockForUpdate()->firstOrFail();
            $cart->items()->whereKey($cartItemId)->firstOrFail()->delete();

            return $this->loadSummary($cart);
        });
    }

    public function clear(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $cart->items()->delete();
            $cart->update(['coupon_code' => null]);

            return $this->loadSummary($cart);
        });
    }

    public function applyCoupon(User $user, string $code): Cart
    {
        return DB::transaction(function () use ($user, $code): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $cart->load('items.purchasable');
            if ($cart->items->isEmpty()) {
                throw ValidationException::withMessages(['cart' => 'The cart is empty.']);
            }
            $normalized = $this->pricing->normalizeCode($code);
            $this->pricing->quote($cart->items, $normalized, $user);
            $cart->update(['coupon_code' => $normalized]);

            return $this->loadSummary($cart);
        }, 3);
    }

    public function removeCoupon(User $user): Cart
    {
        return DB::transaction(function () use ($user): Cart {
            $cart = Cart::query()->firstOrCreate(['user_id' => $user->id]);
            $cart = Cart::query()->lockForUpdate()->findOrFail($cart->id);
            $cart->update(['coupon_code' => null]);

            return $this->loadSummary($cart);
        }, 3);
    }

    public function loadSummary(Cart $cart): Cart
    {
        $cart->load('items.purchasable');
        $available = $cart->items->filter(fn ($item) => $this->catalog->isPurchasable($item->purchasable));
        try {
            $quote = $this->pricing->quote($available, $cart->coupon_code, $cart->user);
            $cart->setAttribute('coupon_error', null);
        } catch (ValidationException $exception) {
            $quote = $this->pricing->quote($available);
            $cart->setAttribute('coupon_error', $exception->errors()['coupon_code'][0] ?? 'Coupon is no longer valid.');
        }
        $cart->setAttribute('estimated_total', $quote['total']);
        $cart->setAttribute('pricing_summary', $quote);

        return $cart;
    }
}
