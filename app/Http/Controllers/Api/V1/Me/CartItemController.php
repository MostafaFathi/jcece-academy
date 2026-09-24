<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreCartItemRequest;
use App\Http\Resources\Api\V1\CartResource;
use App\PurchasableType;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartItemController extends Controller
{
    public function store(StoreCartItemRequest $request, CartService $cartService): CartResource
    {
        $cart = $cartService->add(
            $request->user(),
            PurchasableType::from($request->validated('purchasable_type')),
            (int) $request->validated('purchasable_id'),
        );

        return new CartResource($cart);
    }

    public function destroy(Request $request, int $cartItem, CartService $cartService): CartResource
    {
        return new CartResource($cartService->remove($request->user(), $cartItem));
    }
}
