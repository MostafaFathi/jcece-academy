<?php

namespace App\Services;

use App\Models\Order;
use App\OrderStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderStatusService
{
    public function __construct(private OrderAccessProvisioningService $provisioning) {}

    public function transition(Order $order, OrderStatus $status): Order
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

        return DB::transaction(function () use ($order, $status): Order {
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

            $lockedOrder->update(['status' => $status]);

            return $lockedOrder->refresh();
        });
    }
}
