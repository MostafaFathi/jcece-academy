<?php

namespace App\Jobs;

use App\Events\PlatformNotificationEvent;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Notifications\PlatformEventNotification;
use App\Services\NotificationTargetService;
use App\Services\NotificationTypeRegistry;
use App\UserStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class SendPlatformNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public array $backoff = [60, 300, 900, 1800];

    public function __construct(public int $deliveryId)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationTargetService $targets, NotificationTypeRegistry $registry): void
    {
        $lock = Cache::lock('notification-delivery:'.$this->deliveryId, 90);
        if (! $lock->get()) {
            $this->release(90);

            return;
        }
        try {
            $delivery = NotificationDelivery::query()->find($this->deliveryId);
            if (! $delivery || in_array($delivery->status, ['sent', 'cancelled', 'digest_pending', 'digested', 'failed'], true)) {
                return;
            }
            if ($delivery->available_at->isFuture()) {
                $this->release(max(1, (int) ceil(now()->diffInSeconds($delivery->available_at))));

                return;
            }
            $user = User::query()->find($delivery->user_id);
            $payload = $delivery->payload;
            if (! $user || $user->status !== UserStatus::Active || ! $registry->channels($user, $delivery->type)['email'] || ($delivery->type !== 'digest.daily' && ! $targets->path($user, $payload['target_type'], $payload['target_id']))) {
                $delivery->update(['status' => 'cancelled']);

                return;
            }
            if (RateLimiter::tooManyAttempts('notification-mail:'.$user->id, 30)) {
                $this->release(60);

                return;
            }
            RateLimiter::hit('notification-mail:'.$user->id, 60);
            $delivery->update(['status' => 'sending', 'last_attempt_at' => now(), 'attempts' => $delivery->attempts + 1, 'error_code' => null]);
            try {
                $user->notifyNow((new PlatformEventNotification($payload, ['mail']))->locale($user->preferred_locale));
                $delivery->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $e) {
                $delivery->update(['status' => 'queued', 'error_code' => class_basename($e)]);
                throw $e;
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);
        if ($delivery && $delivery->status !== 'sent') {
            $delivery->update(['status' => 'failed', 'error_code' => class_basename($exception ?? new \RuntimeException)]);
            event(new PlatformNotificationEvent('delivery.failed', $delivery, 'delivery.failed:'.$delivery->id.':'.$delivery->attempts));
        }
    }
}
