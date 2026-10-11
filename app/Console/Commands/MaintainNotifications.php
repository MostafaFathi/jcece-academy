<?php

namespace App\Console\Commands;

use App\Events\PlatformNotificationEvent;
use App\Jobs\SendPlatformNotification;
use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\NotificationDelivery;
use App\Models\NotificationReceipt;
use App\Models\PlatformNotification;
use App\Models\Quiz;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AnnouncementService;
use App\Services\CourseAccessService;
use App\Services\TransactionalDeliveryService;
use App\UserStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaintainNotifications extends Command
{
    protected $signature = 'notifications:maintain {--digest} {--prune}';

    protected $description = 'Publish scheduled announcements, dispatch idempotent reminders and recover notification deliveries';

    public function handle(AnnouncementService $announcements, CourseAccessService $access): int
    {
        foreach (Announcement::query()->where('status', 'scheduled')->where('publish_at', '<=', now())->lazyById(100) as $a) {
            if ($a->expires_at?->isPast()) {
                $a->update(['status' => 'expired']);

                continue;
            }
            $actor = User::query()->find($a->created_by);
            if ($actor && $actor->can('announcements.publish') && $actor->status === UserStatus::Active) {
                try {
                    $announcements->publish($a, $actor);
                } catch (HttpException $exception) {
                    // An author losing course scope must not block unrelated scheduled publications.
                    if (! in_array($exception->getStatusCode(), [403, 404, 422], true)) {
                        throw $exception;
                    }
                }
            }
        }
        Announcement::query()->where('status', 'published')->where('expires_at', '<=', now())->update(['status' => 'expired']);
        foreach (Enrollment::query()->where('status', '!=', 'suspended')->with(['course', 'user', 'currentAccessGrants'])->lazyById(100) as $enrollment) {
            if (! $enrollment->user || ! $enrollment->course || $enrollment->user->status !== UserStatus::Active) {
                continue;
            }
            $state = $access->accessMetadata($enrollment);
            $expires = $state['access_expires_at'];
            if ($state['has_access'] && ! $state['is_lifetime'] && $expires) {
                foreach ([1, 7] as $days) {
                    if ($expires->lte(now()->addDays($days)) && $expires->gt(now())) {
                        event(new PlatformNotificationEvent('enrollment.expiring', $enrollment, 'expiring:'.$enrollment->id.':'.$expires->toIso8601String().':'.$days));
                        break;
                    }
                }
            } elseif ($state['access_state'] === 'expired') {
                $last = $enrollment->accessGrants()->whereNull('revoked_at')->max('access_expires_at');
                event(new PlatformNotificationEvent('enrollment.expired', $enrollment, 'expired:'.$enrollment->id.':'.$last));
            }
        }
        foreach ([[Quiz::class, 'available_until', 'quiz'], [Assignment::class, 'due_at', 'assignment']] as [$model,$deadline,$prefix]) {
            foreach ($model::query()->where('status', 'published')->whereNotNull($deadline)->where($deadline, '>', now())->where($deadline, '<=', now()->addDay())->with('course')->lazyById(100) as $resource) {
                event(new PlatformNotificationEvent($prefix.'.reminder', $resource, 'deadline:'.$prefix.':'.$resource->id.':'.$resource->$deadline->toIso8601String()));
            }
            foreach ($model::query()->where('status', 'published')->whereNotNull('available_from')->where('available_from', '<=', now())->with('course')->lazyById(100) as $resource) {
                event(new PlatformNotificationEvent($prefix.'.available', $resource, 'available:'.$model.':'.$resource->id.':'.$resource->available_from->toIso8601String()));
            }
        }
        foreach (SupportTicket::query()->whereIn('status', ['open', 'in_progress'])->where(function ($q): void {
            $q->where('last_reply_at', '<', now()->subHours(48))->orWhere(fn ($q) => $q->whereNull('last_reply_at')->where('created_at', '<', now()->subHours(48)));
        })->lazyById(100) as $ticket) {
            event(new PlatformNotificationEvent('support.overdue', $ticket, 'overdue:'.$ticket->id.':'.now(config('platform_notifications.timezone'))->toDateString()));
        }
        foreach (NotificationDelivery::query()->whereIn('status', ['queued', 'sending'])->where('available_at', '<=', now())->where(function ($q): void {
            $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', now()->subMinutes(10));
        })->limit(100)->get() as $delivery) {
            SendPlatformNotification::dispatch($delivery->id)->afterCommit();
            $delivery->update(['available_at' => now()->addMinutes(10)]);
        }
        app(TransactionalDeliveryService::class)->dispatchStaleQueued();
        if ($this->option('digest')) {
            $this->digests();
        }
        if ($this->option('prune')) {
            PlatformNotification::query()->whereNotNull('read_at')->where('updated_at', '<', now()->subDays(config('platform_notifications.retention_days')))->delete();
            NotificationDelivery::query()->whereIn('status', ['sent', 'cancelled', 'digested'])->where('updated_at', '<', now()->subDays(config('platform_notifications.retention_days')))->delete();
            NotificationReceipt::query()->where('created_at', '<', now()->subDays(config('platform_notifications.receipt_retention_days')))->delete();
        }
        $this->info('Notification maintenance completed.');

        return self::SUCCESS;
    }

    private function digests(): void
    {
        $date = now(config('platform_notifications.timezone'))->toDateString();
        foreach (NotificationDelivery::query()->where('status', 'digest_pending')->select('user_id')->distinct()->pluck('user_id') as $userId) {
            DB::transaction(function () use ($date, $userId): void {
                User::query()->whereKey($userId)->lockForUpdate()->first();
                $pending = NotificationDelivery::query()->where('user_id', $userId)->where('status', 'digest_pending')->lockForUpdate()->get();
                if ($pending->isEmpty()) {
                    return;
                }
                $digest = NotificationDelivery::query()->firstOrCreate(['user_id' => $userId, 'event_key' => hash('sha256', 'digest:'.$userId.':'.$date), 'channel' => 'email'], ['type' => 'digest.daily', 'payload' => ['event_type' => 'digest.daily', 'digest_count' => $pending->count()], 'available_at' => now()]);
                if (! $digest->wasRecentlyCreated) {
                    return;
                }
                NotificationDelivery::query()->whereIn('id', $pending->pluck('id'))->update(['status' => 'digested']);
                SendPlatformNotification::dispatch($digest->id)->afterCommit();
            });
        }
    }
}
