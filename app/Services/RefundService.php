<?php

namespace App\Services;

use App\AccessGrantSource;
use App\Models\EnrollmentAccessGrant;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\OrderStatus;
use App\PaymentStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function __construct(private AuditTrail $audit, private TransactionalDeliveryService $deliveries) {}

    /** @return array{paid: string, refunded: string, pending: string, refundable: string} */
    public function balance(Order $order): array
    {
        $paid = BigDecimal::zero()->toScale(2);
        if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Refunded], true)) {
            foreach ($order->payments as $payment) {
                if ($payment->status === PaymentStatus::Paid && $payment->currency === $order->currency) {
                    $paid = $paid->plus($payment->amount);
                }
            }
        }
        $completed = BigDecimal::zero()->toScale(2);
        $pending = BigDecimal::zero()->toScale(2);
        foreach ($order->refunds as $refund) {
            if ($refund->status === 'completed') {
                $completed = $completed->plus($refund->amount);
            } elseif ($refund->status === 'pending') {
                $pending = $pending->plus($refund->amount);
            }
        }

        return ['paid' => (string) $paid, 'refunded' => (string) $completed, 'pending' => (string) $pending, 'refundable' => (string) $paid->minus($completed)->minus($pending)];
    }

    /** @param array{amount: string, reason: string, access_effect: string, order_item_ids?: array<int, int>, internal_note?: ?string, external_reference?: ?string} $data */
    public function initiate(Order $order, User $actor, array $data): Refund
    {
        return DB::transaction(function () use ($order, $actor, $data): Refund {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== OrderStatus::Completed || $locked->paid_at === null) {
                throw ValidationException::withMessages(['order' => 'Only completed paid orders can be refunded.']);
            }
            $balance = $this->balance($locked->load('refunds', 'payments'));
            $amount = BigDecimal::of($data['amount']);
            if ($amount->isLessThanOrEqualTo(0) || $amount->isGreaterThan(BigDecimal::of($balance['refundable']))) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the available paid balance.']);
            }
            $effect = $data['access_effect'];
            $ids = array_values(array_unique($data['order_item_ids'] ?? []));
            $hasPendingRefund = ! BigDecimal::of($balance['pending'])->isZero();
            $isFinalRefund = ! $hasPendingRefund && $amount->compareTo(BigDecimal::of($balance['paid'])->minus($balance['refunded'])) === 0;
            if ($hasPendingRefund && $amount->compareTo(BigDecimal::of($balance['refundable'])) === 0) {
                throw ValidationException::withMessages(['amount' => 'Complete or reject pending refunds before reserving the final paid balance.']);
            }
            if ($isFinalRefund && $effect !== 'full') {
                throw ValidationException::withMessages(['access_effect' => 'The final refund must explicitly revoke this order’s purchase access.']);
            }
            if ($effect === 'full' && ! $isFinalRefund) {
                throw ValidationException::withMessages(['access_effect' => 'Full refund must cover the remaining balance.']);
            }
            if ($effect === 'items') {
                if ($ids === [] || $amount->compareTo(BigDecimal::of($balance['refundable'])) >= 0) {
                    throw ValidationException::withMessages(['order_item_ids' => 'Select items for a partial item refund.']);
                }
                $items = $locked->items()->whereIn('id', $ids)->get();
                if ($items->count() !== count($ids)) {
                    throw ValidationException::withMessages(['order_item_ids' => 'Items must belong to this Order.']);
                }
                $allocated = BigDecimal::zero()->toScale(2);
                foreach ($items as $item) {
                    $allocated = $allocated->plus($item->total);
                }
                if ($amount->compareTo($allocated) !== 0 || $locked->refunds()->whereIn('status', ['pending', 'completed'])->where('access_effect', 'items')->get()->contains(fn ($refund) => array_intersect($ids, $refund->order_item_ids ?? []) !== [])) {
                    throw ValidationException::withMessages(['order_item_ids' => 'Refund must equal the selected item net total, and items cannot be refunded twice.']);
                }
            } elseif ($ids !== []) {
                throw ValidationException::withMessages(['order_item_ids' => 'Item selection is only allowed for an item refund.']);
            }
            $refund = $locked->refunds()->create([
                'amount' => (string) $amount->toScale(2), 'currency' => $locked->currency,
                'reason' => $data['reason'], 'internal_note' => $data['internal_note'] ?? null,
                'status' => 'pending', 'access_effect' => $effect, 'order_item_ids' => $ids ?: null,
                'external_reference' => $data['external_reference'] ?? null, 'initiated_by' => $actor->id,
            ]);
            $this->audit->record('refund.initiated', $refund, $actor, ['amount' => $refund->amount, 'currency' => $refund->currency, 'to_status' => 'pending']);

            return $refund;
        }, 3);
    }

    public function complete(Refund $refund, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor): Refund {
            $lockedOrder = Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $lockedRefund = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($lockedRefund->status !== 'pending' || $lockedOrder->status !== OrderStatus::Completed) {
                throw ValidationException::withMessages(['refund' => 'Only pending refunds on completed orders may be completed.']);
            }
            $lockedRefund->update(['status' => 'completed', 'processed_by' => $actor->id, 'processed_at' => now()]);
            $balance = $this->balance($lockedOrder->load('refunds', 'payments'));
            $isFullyRefunded = BigDecimal::of($balance['refunded'])->compareTo(BigDecimal::of($balance['paid'])) === 0;
            if ($isFullyRefunded) {
                $this->revokeItems($lockedOrder, $lockedOrder->items()->pluck('id')->all(), $actor, $lockedRefund);
            } elseif ($lockedRefund->access_effect === 'items') {
                $this->revokeItems($lockedOrder, $lockedRefund->order_item_ids ?? [], $actor, $lockedRefund);
            }
            if ($isFullyRefunded) {
                $lockedOrder->update(['status' => OrderStatus::Refunded]);
            }
            $this->audit->record('refund.completed', $lockedRefund, $actor, ['amount' => $lockedRefund->amount, 'currency' => $lockedRefund->currency, 'to_status' => 'completed']);
            app(FinancialDocumentService::class)->scheduleRefund($lockedRefund);
            $this->deliveries->recordForOrder('refund_completed', 'Refund', $lockedRefund->id, $lockedOrder, ['amount' => $lockedRefund->amount]);

            return $lockedRefund->refresh();
        }, 3);
    }

    public function reject(Refund $refund, User $actor): Refund
    {
        return DB::transaction(function () use ($refund, $actor): Refund {
            Order::query()->lockForUpdate()->findOrFail($refund->order_id);
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->id);
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => 'Only pending refunds may be rejected.']);
            }
            $locked->update(['status' => 'rejected', 'processed_by' => $actor->id, 'processed_at' => now()]);
            $this->audit->record('refund.rejected', $locked, $actor, ['to_status' => 'rejected']);
            $this->deliveries->recordForOrder('refund_rejected', 'Refund', $locked->id, $locked->order);

            return $locked->refresh();
        }, 3);
    }

    /** @param array<int, int> $itemIds */
    private function revokeItems(Order $order, array $itemIds, User $actor, Refund $refund): void
    {
        $grants = EnrollmentAccessGrant::query()->whereIn('source_type', [AccessGrantSource::DirectPurchase->value, AccessGrantSource::PackagePurchase->value])
            ->whereIn('source_id', $itemIds)->whereNull('revoked_at')->lockForUpdate()->get();
        foreach ($grants as $grant) {
            $grant->update(['revoked_at' => now(), 'revoked_by' => $actor->id, 'revocation_reason' => 'Refund #'.$refund->id]);
            $this->audit->record('refund.access_revoked', $grant, $actor, ['refund_id' => $refund->id, 'order_id' => $order->id]);
        }
    }
}
