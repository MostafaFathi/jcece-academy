<?php

namespace App\Services;

use App\Events\PlatformNotificationEvent;
use App\Models\Announcement;
use App\Models\AnnouncementRecipient;
use App\Models\Course;
use App\Models\User;
use App\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AnnouncementService
{
    public function __construct(private CourseAccessService $access, private UnifiedNotificationService $notifications, private AuditTrail $audit) {}

    public function eligible(User $user, array $audience): bool
    {
        if ($user->status !== UserStatus::Active) {
            return false;
        }

        return match ($audience['kind']) {
            'all' => true,
            'roles' => $user->hasAnyRole($audience['roles'] ?? []),
            'course_students' => Course::query()->whereIn('id', $audience['course_ids'] ?? [])->get()->contains(fn (Course $course): bool => $this->access->hasAccess($user, $course)),
            'course_instructors' => Course::query()->whereIn('id', $audience['course_ids'] ?? [])->where('instructor_id', $user->id)->get()->contains(fn (Course $course): bool => $user->hasRole('instructor') && Gate::forUser($user)->allows('view', $course)),
            default => false,
        };
    }

    public function audience(array $audience): \Generator
    {
        $query = User::query()->where('status', UserStatus::Active)->with(['roles.permissions', 'permissions']);
        if ($audience['kind'] === 'roles') {
            $query->whereHas('roles', fn ($q) => $q->whereIn('name', $audience['roles']));
        }
        if ($audience['kind'] === 'course_students') {
            $query->whereHas('enrollments', fn ($q) => $q->whereIn('course_id', $audience['course_ids']));
        }
        if ($audience['kind'] === 'course_instructors') {
            $query->whereHas('instructedCourses', fn ($q) => $q->whereIn('id', $audience['course_ids']));
        }
        foreach ($query->lazyById(100) as $user) {
            if ($this->eligible($user, $audience)) {
                yield $user;
            }
        }
    }

    public function publish(Announcement $announcement, User $actor): Announcement
    {
        abort_unless($actor->can('announcements.publish') && $actor->status === UserStatus::Active, 403);

        return DB::transaction(function () use ($announcement, $actor): Announcement {
            $locked = Announcement::query()->lockForUpdate()->findOrFail($announcement->id);
            if ($locked->status === 'published') {
                return $locked;
            }
            abort_unless(in_array($locked->status, ['draft', 'scheduled'], true), 422);
            abort_if($locked->expires_at?->isPast(), 422);
            foreach ($locked->audience['course_ids'] ?? [] as $id) {
                $course = Course::query()->findOrFail($id);
                abort_unless(Gate::forUser($actor)->allows('view', $course), 403);
            }
            $locked->update(['status' => 'published', 'published_at' => now(), 'published_by' => $actor->id]);
            foreach ($this->audience($locked->audience) as $user) {
                AnnouncementRecipient::query()->firstOrCreate(['announcement_id' => $locked->id, 'user_id' => $user->id]);
                $this->notifications->deliver($user, new PlatformNotificationEvent('announcement.published', $locked, 'announcement:'.$locked->id));
            }
            $this->audit->record('announcement.published', $locked, $actor, ['to_status' => 'published']);

            return $locked;
        });
    }

    public function canRead(User $user, Announcement $announcement): bool
    {
        return $announcement->status === 'published' && (! $announcement->expires_at || $announcement->expires_at->isFuture()) && AnnouncementRecipient::query()->where('announcement_id', $announcement->id)->where('user_id', $user->id)->exists() && $this->eligible($user, $announcement->audience);
    }
}
