<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\OrderStatus;
use App\PaymentMethod;
use App\PaymentStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PaymentSubmissionService
{
    public function submit(
        Order $order,
        User $user,
        PaymentMethod $method,
        UploadedFile $proof,
        ?string $transactionId = null,
    ): Payment {
        $disk = (string) config('jcec.commerce.payment_proof_disk', 'local');
        $proofPath = null;

        try {
            return DB::transaction(function () use ($order, $user, $method, $proof, $transactionId, $disk, &$proofPath): Payment {
                $lockedOrder = Order::query()->whereBelongsTo($user)->lockForUpdate()->findOrFail($order->id);

                if (! in_array($lockedOrder->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment], true)) {
                    throw ValidationException::withMessages([
                        'order' => 'Payment cannot be submitted for this order status.',
                    ]);
                }

                if ($lockedOrder->payments()->where('status', PaymentStatus::PendingReview)->exists()) {
                    throw ValidationException::withMessages([
                        'order' => 'This order already has a payment awaiting review.',
                    ]);
                }

                $proofPath = $proof->store('payment-proofs', $disk);

                if ($proofPath === false) {
                    throw new RuntimeException('The payment proof could not be stored.');
                }

                $payment = $lockedOrder->payments()->create([
                    'transaction_id' => $transactionId,
                    'method' => $method,
                    'gateway' => null,
                    'amount' => $lockedOrder->total,
                    'currency' => $lockedOrder->currency,
                    'status' => PaymentStatus::PendingReview,
                    'payment_proof' => $proofPath,
                ]);

                if ($lockedOrder->status === OrderStatus::Pending) {
                    $lockedOrder->update(['status' => OrderStatus::AwaitingPayment]);
                }

                return $payment->load('order');
            }, 3);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk($disk)->delete($proofPath);
            }

            throw $exception;
        }
    }
}
