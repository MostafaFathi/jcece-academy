<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\CheckoutRequest;
use App\Http\Resources\Api\V1\MeOrderResource;
use App\Models\Order;
use App\Services\OrderCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CheckoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CheckoutRequest $request, OrderCheckoutService $checkoutService): JsonResponse
    {
        Gate::authorize('create', Order::class);
        $order = $checkoutService->checkout($request->user(), $request->validated());
        $status = $order->wasRecentlyCreated ? 201 : 200;

        return (new MeOrderResource($order))->response()->setStatusCode($status);
    }
}
