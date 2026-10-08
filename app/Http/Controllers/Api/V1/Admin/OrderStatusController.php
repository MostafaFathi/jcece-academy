<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateOrderStatusRequest;
use App\Http\Resources\Api\V1\AdminOrderResource;
use App\Models\Order;
use App\OrderStatus;
use App\Services\OrderStatusService;
use Illuminate\Support\Facades\Gate;

class OrderStatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateOrderStatusRequest $request,
        Order $order,
        OrderStatusService $statusService,
    ): AdminOrderResource {
        Gate::authorize('manage', $order);
        $order = $statusService->transition($order, OrderStatus::from($request->validated('status')), $request->user());

        return new AdminOrderResource($order);
    }
}
