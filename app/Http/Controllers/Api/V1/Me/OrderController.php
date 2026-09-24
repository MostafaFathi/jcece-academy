<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MeOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = Order::query()
            ->whereBelongsTo($request->user())
            ->latest()
            ->orderByDesc('id')
            ->paginate(15);

        return MeOrderResource::collection($orders);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Order $order): MeOrderResource
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return new MeOrderResource($order->load(['items.packageCourses', 'payments']));
    }
}
