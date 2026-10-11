<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminTransactionalDeliveryResource;
use App\Models\Order;
use App\Models\TransactionalDelivery;
use App\PermissionName;
use App\Services\AuditTrail;
use App\Services\TransactionalDeliveryService;
use Illuminate\Http\Request;

class TransactionalDeliveryRetryController extends Controller
{
    public function __invoke(Request $request, Order $order, TransactionalDelivery $delivery, AuditTrail $audit): AdminTransactionalDeliveryResource
    {
        abort_unless($request->user()->can(PermissionName::TransactionalDeliveriesRetry->value), 403);
        abort_unless($delivery->order_id === $order->id, 404);

        $delivery = app(TransactionalDeliveryService::class)->retry($delivery, $request->user(), $audit);

        return new AdminTransactionalDeliveryResource($delivery);
    }
}
