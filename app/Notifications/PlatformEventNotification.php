<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PlatformEventNotification extends Notification
{
    public function __construct(public array $payload, public array $channels = ['database']) {}

    public function via(object $notifiable): array
    {
        return $this->channels;
    }

    public function databaseType(object $notifiable): string
    {
        return $this->payload['event_type'];
    }

    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = in_array($notifiable->preferred_locale, ['ar', 'en'], true) ? $notifiable->preferred_locale : 'ar';
        // Dotted event identifiers are literal array keys, not config path segments.
        $definition = config('platform_notifications.types')[$this->payload['event_type']];
        $message = (new MailMessage)->subject($definition[$locale])->line($definition[$locale]);
        if (isset($this->payload['digest_count'])) {
            $message->line((string) $this->payload['digest_count']);
        }

        return $message->action($locale === 'ar' ? 'عرض الإشعارات' : 'View notifications', url('/notifications'));
    }
}
