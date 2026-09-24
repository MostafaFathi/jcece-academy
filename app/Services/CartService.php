<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\User;
use App\PurchasableType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;

class CartService
{
    public function __construct(private CommerceCatalogService $catalog) {}

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

            return $this->loadSummary($cart);
        });
    }

    public function loadSummary(Cart $cart): Cart
    {
        $cart->load('items.purchasable');
        $total = BigDecimal::zero()->toScale(2);

        foreach ($cart->items as $item) {
            if ($this->catalog->isPurchasable($item->purchasable)) {
                $total = $total->plus(BigDecimal::of($item->purchasable->price));
            }
        }

        $cart->setAttribute('estimated_total', (string) $total->toScale(2, RoundingMode::Unnecessary));

        return $cart;
    }
}
