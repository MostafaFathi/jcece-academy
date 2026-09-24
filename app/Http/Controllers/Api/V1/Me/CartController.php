<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CartResource;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index(Request $request, CartService $cartService): CartResource
    {
        return new CartResource($cartService->get($request->user()));
    }

    public function destroy(Request $request, CartService $cartService): CartResource
    {
        return new CartResource($cartService->clear($request->user()));
    }
}
