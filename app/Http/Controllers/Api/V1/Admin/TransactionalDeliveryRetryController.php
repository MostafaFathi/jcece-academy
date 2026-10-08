<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AdminTransactionalDeliveryResource;
use App\Jobs\SendTransactionalDelivery;
use App\Models\Order;
use App\Models\TransactionalDelivery;
use App\PermissionName;
use App\Services\AuditTrail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionalDeliveryRetryController extends Controller
{
    public function __invoke(Request $request, Order $order, TransactionalDelivery $delivery, AuditTrail $audit): AdminTransactionalDeliveryResource
    {
        abort_unless($request->user()->can(PermissionName::TransactionalDeliveriesRetry->value), 403);
        abort_unless($delivery->order_id === $order->id, 404);

        $delivery = DB::transaction(function () use ($delivery, $request, $audit): TransactionalDelivery {
            $locked = TransactionalDelivery::query()->lockForUpdate()->findOrFail($delivery->id);
            abort_unless($locked->status === 'failed' || ($locked->status === 'queued' && $locked->queued_at->lessThan(now()->subMinutes(10))), 409);
            $locked->update(['status' => 'queued', 'queued_at' => now(), 'error_code' => null]);
            $audit->record('transactional_delivery.retry_requested', $locked, $request->user(), ['order_id' => $locked->order_id]);
            DB::afterCommit(function () use ($locked): void {
                try {
                    SendTransactionalDelivery::dispatch($locked->id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });

            return $locked->refresh();
        });

        return new AdminTransactionalDeliveryResource($delivery);
    }
}
