<?php

namespace App\Services;

use App\Events\PlatformNotificationEvent;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class NotificationRecipientResolver
{
    public function __construct(private NotificationTargetService $targets, private CourseAccessService $access) {}

    private function permission(Builder $query, string ...$permissions): Builder
    {
        foreach ($permissions as $permission) {
            $query->where(function (Builder $q) use ($permission): void {
                $q->whereHas('permissions', fn ($p) => $p->where('name', $permission)->where('guard_name', 'web'))
                    ->orWhereHas('roles.permissions', fn ($p) => $p->where('name', $permission)->where('guard_name', 'web'));
            });
        }

        return $query;
    }

    public function recipients(PlatformNotificationEvent $event): \Generator
    {
        $type = $event->type;
        $subject = $event->subject;
        if ($subject instanceof EnrollmentAccessGrant) {
            $subject = $subject->enrollment()->first();
            if (! $subject) {
                return;
            }
        }
        if ($subject instanceof SupportTicketMessage) {
            $ticket = $subject->ticket()->first();
            if (! $ticket) {
                return;
            }
        } else {
            $ticket = $subject;
        }
        $course = $this->targets->course($subject);
        $query = User::query()->where('status', UserStatus::Active)->with(['roles.permissions', 'permissions']);
        $owner = $subject->user_id ?? (method_exists($subject, 'order') ? $subject->order()->first()?->user_id : null);
        // Query candidate sets before evaluating resource policies; never broadcast to a role name alone.
        switch ($type) {
            case 'message.received':
                $conversation = $subject->conversation()->with('course')->first();
                if (! $conversation) {
                    return;
                }
                $query->where('id', '!=', $subject->user_id)->where(function ($q) use ($conversation): void {
                    $q->where('id', $conversation->instructor_id)->orWhereHas('enrollments', fn ($e) => $e->where('course_id', $conversation->course_id));
                });
                break;
            case 'enrollment.student_joined':$query->whereKey($course?->instructor_id);
                break;
            case 'course.assigned':$query->whereIn('id', array_filter([$course?->instructor_id, $event->context['previous_instructor_id'] ?? null]));
                break;
            case 'lesson.published':case 'quiz.available':case 'assignment.available':case 'quiz.reminder':case 'assignment.reminder':
                if (! $course) {
                    return;
                }$query->whereHas('enrollments', fn ($e) => $e->where('course_id', $course->id));
                break;
            case 'assignment.submitted':$this->permission($query, 'assignment_submissions.grade');
                break;
            case 'certificate.requested':$this->permission($query, 'certificates.issue', 'certificates.view');
                break;
            case 'payment.pending':$this->permission($query, 'payments.manage', 'payments.view');
                break;
            case 'refund.pending':$this->permission($query, 'refunds.manage', 'orders.view');
                break;
            case 'review.pending':$this->permission($query, 'reviews.view');
                break;
            case 'video.failed':case 'course.updated':$this->permission($query, 'courses.view');
                break;
            case 'support.escalated':$this->permission($query, 'support_tickets.manage', 'support_tickets.view');
                break;
            case 'support.created':case 'support.overdue':$this->permission($query, 'support_tickets.view', 'support_tickets.reply');
                if ($ticket->assigned_to) {
                    $query->whereKey($ticket->assigned_to);
                }break;
            case 'support.assigned':$this->permission($query->whereKey($ticket->assigned_to), 'support_tickets.view');
                break;
            case 'support.reply':
                if ($subject->user_id === $ticket->user_id || $subject->is_internal) {
                    $this->permission($query, 'support_tickets.view', 'support_tickets.reply');
                    if ($ticket->assigned_to) {
                        $query->whereKey($ticket->assigned_to);
                    }
                } else {
                    $query->whereKey($ticket->user_id);
                }break;
            case 'support.updated':$query->whereIn('id', array_filter([$ticket->user_id, $ticket->assigned_to]));
                break;
            case 'delivery.failed':case 'system.critical':$this->permission($query, 'notifications.manage');
                break;
            case 'security.permissions_changed':$this->permission($query, 'notifications.security.view');
                break;
            case 'security.changed':$query->where(function ($q) use ($subject): void {
                $q->whereKey($subject->id)->orWhereHas('permissions', fn ($p) => $p->where('name', 'notifications.security.view'))->orWhereHas('roles.permissions', fn ($p) => $p->where('name', 'notifications.security.view'));
            });
                break;
            case 'announcement.published':$query->whereHas('roles');
                break;
            default:$query->whereKey($owner);
        }
        foreach ($query->lazyById(100) as $user) {
            if ($type === 'message.received' && ! Gate::forUser($user)->allows('view', $conversation)) {
                continue;
            }
            if (in_array($type, ['lesson.published', 'quiz.available', 'assignment.available', 'quiz.reminder', 'assignment.reminder', 'enrollment.student_joined'], true)) {
                if ($type === 'enrollment.student_joined') {
                    if (! $user->can('courses.view') || ! Gate::forUser($user)->allows('view', $course)) {
                        continue;
                    }
                } elseif (! $this->access->hasAccess($user, $course)) {
                    continue;
                }
                if (str_starts_with($type, 'quiz.') && ! app(QuizAccessService::class)->isAvailableTo($user, $subject)) {
                    continue;
                }
                if (str_starts_with($type, 'assignment.') && ! app(AssignmentAccessService::class)->isAvailableTo($user, $subject)) {
                    continue;
                }
            }
            if ($type === 'quiz.reminder' && $subject->attempts()->where('user_id', $user->id)->where('status', 'submitted')->exists()) {
                continue;
            }
            if ($type === 'assignment.reminder' && $subject->submissions()->where('user_id', $user->id)->whereIn('status', ['submitted', 'graded'])->exists()) {
                continue;
            }
            if ($type === 'assignment.submitted' && ! Gate::forUser($user)->allows('grade', $subject)) {
                continue;
            }
            if (in_array($type, ['course.updated', 'video.failed'], true) && (! $course || ! $user->can('courses.update') || ! Gate::forUser($user)->allows('view', $course))) {
                continue;
            }
            if ($type === 'course.updated' && $subject instanceof Lesson && ! $user->can('curriculum.view')) {
                continue;
            }
            if ($type === 'review.pending' && ! ($user->can('reviews.moderate') || ($course?->instructor_id === $user->id && $user->can('courses.view')))) {
                continue;
            }
            if ($type === 'support.reply' && $user->id === $subject->user_id) {
                continue;
            }
            yield $user;
        }
    }
}
