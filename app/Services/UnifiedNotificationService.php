<?php

namespace App\Services;

use App\Events\PlatformNotificationEvent;
use App\Jobs\SendPlatformNotification;
use App\Models\EnrollmentAccessGrant;
use App\Models\NotificationDelivery;
use App\Models\NotificationReceipt;
use App\Models\PlatformNotification;
use App\Models\User;
use App\Notifications\PlatformEventNotification;
use App\UserStatus;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnifiedNotificationService
{
    public function __construct(private NotificationTypeRegistry $registry, private NotificationTargetService $targets, private RealtimeSignals $realtime, private NotificationContentService $content) {}

    public function deliver(User $recipient, PlatformNotificationEvent $event): void
    {
        DB::transaction(function () use ($recipient, $event): void {
            $user = User::query()->lockForUpdate()->find($recipient->id);
            if (! $user || $user->status !== UserStatus::Active) {
                return;
            }
            $definition = $this->registry->get($event->type);
            $key = hash('sha256', $event->eventKey);
            $receipt = NotificationReceipt::query()->firstOrCreate(['user_id' => $user->id, 'event_key' => $key], ['type' => $event->type]);
            if (! $receipt->wasRecentlyCreated) {
                return;
            }
            $subject = $event->subject instanceof EnrollmentAccessGrant ? $event->subject->enrollment()->first() : $event->subject;
            if (! $subject) {
                return;
            }
            [$targetType,$targetId] = $this->targets->target($subject);
            if ($event->type === 'enrollment.student_joined') {
                [$targetType,$targetId] = ['course', $subject->course_id];
            }
            if ($event->type === 'certificate.eligible') {
                [$targetType,$targetId] = ['enrollment', $subject->id];
            }
            $payload = ['event_type' => $event->type, 'target_type' => $targetType, 'target_id' => $targetId, 'context' => $this->content->snapshot($event->subject)];
            $channels = $this->registry->channels($user, $event->type);
            if ($event->type === 'message.received' && Cache::get("notification-presence:{$user->id}:{$targetId}")) {
                return;
            }
            $notification = null;
            if ($channels['in_app']) {
                if ($event->type === 'message.received') {
                    $notification = PlatformNotification::query()->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->where('type', $event->type)->where('target_type', $targetType)->where('target_id', $targetId)->whereNull('read_at')->lockForUpdate()->first();
                }
                if ($notification) {
                    $notification->increment('aggregate_count');
                    $notification->update(['target_version' => $subject->id, 'updated_at' => now()]);
                } else {
                    $native = new PlatformEventNotification($payload);
                    $native->id = (string) Str::uuid();
                    app(DatabaseChannel::class)->send($user, $native);
                    $notification = PlatformNotification::query()->findOrFail($native->id);
                    $notification->forceFill(['category' => $definition['category'], 'priority' => $definition['priority'], 'target_type' => $targetType, 'target_id' => $targetId, 'target_version' => $event->type === 'message.received' ? $subject->id : 0])->save();
                }
                $receipt->update(['notification_id' => $notification->id]);
                $this->realtime->notification($user->id);
            }
            // Existing commerce/certificate outbox owns these emails and is monitored alongside this outbox.
            $legacy = in_array($event->type, ['payment.approved', 'payment.rejected', 'order.updated', 'refund.updated', 'certificate.approved', 'certificate.issued'], true);
            if ($channels['email'] && ! $legacy) {
                // One optional conversation email per five-minute activity window.
                $availableAt = now();
                if ($event->type === 'message.received') {
                    $key = hash('sha256', 'conversation-email:'.$targetId.':'.intdiv(now()->timestamp, 300));
                    $availableAt = now()->addMinutes(5);
                }
                $delivery = NotificationDelivery::query()->firstOrCreate(['user_id' => $user->id, 'event_key' => $key, 'channel' => 'email'], ['type' => $event->type, 'payload' => $payload, 'status' => $channels['digest'] && ! $definition['mandatory'] ? 'digest_pending' : 'queued', 'available_at' => $availableAt]);
                if ($delivery->wasRecentlyCreated && $delivery->status === 'queued') {
                    SendPlatformNotification::dispatch($delivery->id)->delay($availableAt)->afterCommit();
                }
            }
        }, 3);
    }
}
