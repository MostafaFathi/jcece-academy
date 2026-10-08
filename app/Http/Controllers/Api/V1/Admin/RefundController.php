<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreRefundRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundController extends Controller
{
    public function store(StoreRefundRequest $request, Order $order, RefundService $service): JsonResponse
    {
        return response()->json($service->initiate($order, $request->user(), $request->validated()), 201);
    }

    public function complete(Request $request, Order $order, Refund $refund, RefundService $service): JsonResponse
    {
        abort_unless($request->user()->can('refunds.manage'), 403);
        abort_unless($refund->order_id === $order->id, 404);

        return response()->json($service->complete($refund, $request->user()));
    }

    public function reject(Request $request, Order $order, Refund $refund, RefundService $service): JsonResponse
    {
        abort_unless($request->user()->can('refunds.manage'), 403);
        abort_unless($refund->order_id === $order->id, 404);

        return response()->json($service->reject($refund, $request->user()));
    }
}
