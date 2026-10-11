<?php

namespace App\Services;

use App\Events\CourseConversationChanged;
use App\Events\UserNotificationChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

class RealtimeSignals
{
    public function conversation(int $conversationId, int $version, string $kind): void
    {
        if (config('messaging.broadcast')) {
            $this->dispatchAfterCommit(new CourseConversationChanged($conversationId, $version, $kind));
        }
    }

    public function notification(int $userId): void
    {
        if (config('platform_notifications.broadcast')) {
            $this->dispatchAfterCommit(new UserNotificationChanged($userId));
        }
    }

    private function dispatchAfterCommit(object $event): void
    {
        if (config('broadcasting.default') !== 'pusher'
            || ! config('broadcasting.connections.pusher.key')
            || ! config('broadcasting.connections.pusher.secret')
            || ! config('broadcasting.connections.pusher.app_id')
            || ! config('broadcasting.connections.pusher.options.cluster')) {
            return;
        }

        DB::afterCommit(function () use ($event): void {
            try {
                Event::dispatch($event);
            } catch (Throwable $exception) {
                Log::warning('Realtime broadcast failed; polling remains available.', [
                    'event' => $event::class,
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
