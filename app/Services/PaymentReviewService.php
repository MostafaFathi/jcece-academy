<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReviewService
{
    public function approve(Payment $payment, User $approver): Payment
    {
        return DB::transaction(function () use ($payment, $approver): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if (
                $lockedPayment->status === PaymentStatus::Paid
                && in_array($order->status, [OrderStatus::Paid, OrderStatus::Completed], true)
            ) {
                return $lockedPayment->load(['order', 'approver']);
            }

            if ($lockedPayment->status !== PaymentStatus::PendingReview) {
                throw ValidationException::withMessages(['payment' => 'Only payments awaiting review may be approved.']);
            }

            if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], true)) {
                throw ValidationException::withMessages(['order' => 'This order cannot accept a payment approval.']);
            }

            if (
                BigDecimal::of($lockedPayment->amount)->compareTo(BigDecimal::of($order->total)) !== 0
                || $lockedPayment->currency !== $order->currency
            ) {
                throw ValidationException::withMessages(['payment' => 'The payment must match the full order amount and currency.']);
            }

            if ($order->payments()->whereKeyNot($lockedPayment->id)->where('status', PaymentStatus::Paid)->exists()) {
                throw ValidationException::withMessages(['payment' => 'This order already has a successful payment.']);
            }

            $approvedAt = now();
            $lockedPayment->update([
                'status' => PaymentStatus::Paid,
                'paid_at' => $approvedAt,
                'approved_by' => $approver->id,
                'approved_at' => $approvedAt,
                'rejection_reason' => null,
            ]);
            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => $approvedAt,
            ]);

            return $lockedPayment->refresh()->load(['order', 'approver']);
        }, 3);
    }

    public function reject(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($lockedPayment->order_id);

            if ($lockedPayment->status === PaymentStatus::Rejected) {
                return $lockedPayment->load('order');
            }

            if ($lockedPayment->status !== PaymentStatus::PendingReview) {
                throw ValidationException::withMessages(['payment' => 'Only payments awaiting review may be rejected.']);
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Rejected,
                'rejection_reason' => $reason,
            ]);

            if ($order->status === OrderStatus::AwaitingPayment) {
                $order->update(['status' => OrderStatus::Pending]);
            }

            return $lockedPayment->refresh()->load('order');
        }, 3);
    }
}
