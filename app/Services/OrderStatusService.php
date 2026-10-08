<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(private OrderAccessProvisioningService $provisioning, private AuditTrail $audit, private TransactionalDeliveryService $deliveries) {}

    public function transition(Order $order, OrderStatus $status, ?User $actor = null): Order
    {
        if ($status === OrderStatus::Completed) {
            $currentOrder = $order->fresh();

            if ($currentOrder->status !== OrderStatus::Paid) {
                throw ValidationException::withMessages([
                    'status' => "The order cannot transition from {$currentOrder->status->value} to {$status->value}.",
                ]);
            }

            return $this->provisioning->provision($order)['order'];
        }

        return DB::transaction(function () use ($order, $status, $actor): Order {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
            $allowed = match ($lockedOrder->status) {
                OrderStatus::Pending, OrderStatus::AwaitingPayment => [OrderStatus::Cancelled],
                OrderStatus::Paid => [OrderStatus::Completed],
                default => [],
            };

            if (! in_array($status, $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "The order cannot transition from {$lockedOrder->status->value} to {$status->value}.",
                ]);
            }

            $previous = $lockedOrder->status;
            $lockedOrder->update(['status' => $status]);
            if ($status === OrderStatus::Cancelled) {
                $lockedOrder->couponRedemption()->where('status', 'reserved')->update(['status' => 'released']);
                $this->deliveries->recordForOrder('order_cancelled', 'Order', $lockedOrder->id, $lockedOrder);
            }
            $this->audit->record('order.status_changed', $lockedOrder, $actor, ['from_status' => $previous->value, 'to_status' => $status->value]);

            return $lockedOrder->refresh();
        });
    }
}
