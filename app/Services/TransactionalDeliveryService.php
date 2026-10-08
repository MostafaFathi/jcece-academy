<?php

namespace App\Services;

use App\Jobs\SendTransactionalDelivery;
use App\Models\Order;
use App\Models\TransactionalDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionalDeliveryService
{
    public function dispatchStaleQueued(): int
    {
        $deliveryIds = TransactionalDelivery::query()
            ->where('status', 'queued')
            ->where('queued_at', '<', now()->subMinutes(10))
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');
        $dispatched = 0;

        foreach ($deliveryIds as $deliveryId) {
            try {
                SendTransactionalDelivery::dispatch($deliveryId);
                TransactionalDelivery::query()->whereKey($deliveryId)->where('status', 'queued')->update(['queued_at' => now()]);
                $dispatched++;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $dispatched;
    }

    /** @param array<string, int|string|null> $payload */
    public function recordForOrder(string $event, string $subjectType, int $subjectId, Order $order, array $payload = []): TransactionalDelivery
    {
        return $this->record(
            "{$event}:{$subjectType}:{$subjectId}", $event, $order->customer_email,
            $this->locale($order->locale_snapshot), $subjectType, $subjectId,
            ['name' => $order->customer_name, 'order_number' => $order->order_number, 'amount' => $order->total, 'currency' => $order->currency, ...$payload],
            $order->user_id, $order->id,
        );
    }

    /** @param array<string, int|string|null> $payload */
    public function recordForUser(string $event, string $subjectType, int $subjectId, User $user, array $payload = []): TransactionalDelivery
    {
        return $this->record(
            "{$event}:{$subjectType}:{$subjectId}", $event, $user->email,
            $this->locale($user->preferred_locale), $subjectType, $subjectId,
            ['name' => $user->name, ...$payload], $user->id, null,
        );
    }

    /** @param array<string, int|string|null> $payload */
    private function record(string $eventKey, string $event, string $email, string $locale, string $subjectType, int $subjectId, array $payload, ?int $userId, ?int $orderId): TransactionalDelivery
    {
        $delivery = TransactionalDelivery::query()->firstOrCreate(['event_key' => $eventKey], [
            'message_type' => $event,
            'user_id' => $userId,
            'order_id' => $orderId,
            'recipient_email' => $email,
            'locale' => $locale,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'status' => 'queued',
            'queued_at' => now(),
        ]);

        if ($delivery->wasRecentlyCreated) {
            DB::afterCommit(function () use ($delivery): void {
                try {
                    SendTransactionalDelivery::dispatch($delivery->id);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        return $delivery;
    }

    private function locale(?string $locale): string
    {
        return in_array($locale, ['ar', 'en'], true) ? $locale : 'ar';
    }
}
