<?php

namespace App\Listeners;

use App\Events\PlatformNotificationEvent;
use App\Jobs\SendPlatformNotification;
use App\Models\NotificationDelivery;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyPlatformJobFailure
{
    public function handle(JobFailed $event): void
    {
        // Notification failures have their own tracked event; never recursively alert on alert delivery.
        if ($event->job->resolveName() === SendPlatformNotification::class) {
            return;
        }
        try {
            $subject = (new NotificationDelivery)->forceFill(['id' => 0]);
            event(new PlatformNotificationEvent('system.critical', $subject, 'queue-failed:'.($event->job->uuid() ?? $event->job->getJobId())));
        } catch (Throwable $exception) {
            // A database outage must not mask the original queue failure or reveal its payload.
            Log::warning('Unable to persist platform failure notification.', ['error_code' => class_basename($exception)]);
        }
    }
}
