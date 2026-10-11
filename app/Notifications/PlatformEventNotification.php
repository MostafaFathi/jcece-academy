<?php

namespace App\Notifications;

use App\Services\NotificationContentService;
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
        $content = app(NotificationContentService::class)->present($this->payload['event_type'], $this->payload['context'] ?? [], $locale);
        $message = (new MailMessage)->subject($content['title'])->line($content['title']);
        if ($content['description'] !== '') {
            $message->line($content['description']);
        }
        if (isset($this->payload['digest_count'])) {
            $message->line((string) $this->payload['digest_count']);
        }

        return $message->action($locale === 'ar' ? 'عرض الإشعارات' : 'View notifications', url('/notifications'));
    }
}
