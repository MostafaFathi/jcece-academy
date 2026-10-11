<?php

namespace App\Listeners;

use App\Events\PlatformNotificationEvent;
use App\Services\NotificationRecipientResolver;
use App\Services\UnifiedNotificationService;

class PersistPlatformNotification
{
    public function __construct(private NotificationRecipientResolver $recipients, private UnifiedNotificationService $notifications) {}

    public function handle(PlatformNotificationEvent $event): void
    {
        foreach ($this->recipients->recipients($event) as $recipient) {
            $this->notifications->deliver($recipient, $event);
        }
    }
}
