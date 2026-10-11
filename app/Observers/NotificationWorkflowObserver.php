<?php

namespace App\Observers;

use App\Events\PlatformNotificationEvent;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\CourseMessage;
use App\Models\CourseReadCursor;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonVideoUpload;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PlatformNotification;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TransactionalDelivery;
use App\Models\User;
use App\Services\CertificateEligibilityService;
use App\Services\CourseAccessService;
use Illuminate\Database\Eloquent\Model;

class NotificationWorkflowObserver
{
    public function __construct(private CourseAccessService $access) {}

    public function created(Model $model): void
    {
        $this->changed($model, true);
    }

    public function updated(Model $model): void
    {
        $this->changed($model, false);
    }

    private function changed(Model $model, bool $created): void
    {
        $status = $model->status instanceof \BackedEnum ? $model->status->value : $model->status;
        $changed = $created || $model->wasChanged('status');
        $types = [];
        $context = [];
        if ($model instanceof AuditEvent && $created) {
            if (in_array($model->event_type, ['user.role_added', 'user.role_removed'], true)) {
                $user = User::query()->find($model->subject_id);
                if ($user) {
                    event(new PlatformNotificationEvent('security.changed', $user, 'security-audit:'.$model->id));
                }
            } elseif ($model->event_type === 'role.permissions_changed') {
                event(new PlatformNotificationEvent('security.permissions_changed', $model, 'security-audit:'.$model->id));
            }

            return;
        }
        if ($model instanceof EnrollmentAccessGrant) {
            $enrollment = $model->enrollment()->with('course')->first();
            if ($enrollment && $this->access->enrollmentHasAccess($enrollment) && ($created || $model->wasChanged(['access_starts_at', 'access_expires_at', 'revoked_at']))) {
                $types = ['enrollment.activated', 'enrollment.student_joined'];
            } elseif (! $created && $model->wasChanged('revoked_at') && $enrollment && ! $this->access->enrollmentHasAccess($enrollment)) {
                $types = ['enrollment.suspended'];
            }
        } elseif ($model instanceof Enrollment) {
            if (! $created && $changed) {
                $types = $status === 'suspended' ? ['enrollment.suspended'] : ($this->access->enrollmentHasAccess($model) ? ['enrollment.activated'] : []);
            }
        } elseif ($model instanceof Course) {
            if ($model->instructor_id && ($created || $model->wasChanged('instructor_id'))) {
                $types[] = 'course.assigned';
                $context['previous_instructor_id'] = $model->getOriginal('instructor_id');
            }
            if ($created || $model->wasChanged(['status', 'title', 'description'])) {
                $types[] = 'course.updated';
            }
        } elseif ($model instanceof Lesson) {
            if ($model->is_published && ($created || $model->wasChanged('is_published'))) {
                $types = ['lesson.published'];
            }
            if ($created || $model->wasChanged(['title', 'content', 'is_published', 'video_provider', 'video_guid'])) {
                $types[] = 'course.updated';
            }
        } elseif ($model instanceof Quiz || $model instanceof Assignment) {
            if ($changed && $status === 'published') {
                $types = [$model instanceof Quiz ? 'quiz.available' : 'assignment.available'];
            }
        } elseif ($model instanceof QuizAttempt) {
            if ($changed && in_array($status, ['submitted', 'expired'], true) && $model->show_results) {
                $types = ['quiz.result'];
            }
        } elseif ($model instanceof AssignmentSubmission) {
            if ($changed || $model->wasChanged('graded_at')) {
                $types = match ($status) {
                    'submitted' => ['assignment.submitted'],'graded' => ['assignment.graded'],'revision_requested' => ['assignment.revision'],default => []
                };
            }
        } elseif ($model instanceof CertificateApprovalRequest) {
            if ($changed) {
                $types = [$status === 'approved' ? 'certificate.approved' : 'certificate.requested'];
            }
        } elseif ($model instanceof Certificate) {
            if ($changed) {
                $types = [$status === 'revoked' ? 'certificate.revoked' : 'certificate.issued'];
            }
        } elseif ($model instanceof Payment) {
            if ($changed) {
                $types = match ($status) {
                    'paid' => ['payment.approved'],'rejected' => ['payment.rejected'],'pending_review' => ['payment.pending'],default => []
                };
            }
        } elseif ($model instanceof Refund) {
            if ($changed) {
                $types = [$status === 'pending' ? 'refund.pending' : 'refund.updated'];
            }
        } elseif ($model instanceof Order) {
            if (! $created && $changed) {
                $types = ['order.updated'];
            }
        } elseif ($model instanceof SupportTicket) {
            if ($created) {
                $types[] = 'support.created';
            } elseif ($changed) {
                $types[] = 'support.updated';
            }if ($model->assigned_to && ($created || $model->wasChanged('assigned_to'))) {
                $types[] = 'support.assigned';
            }
        } elseif ($model instanceof SupportTicketMessage) {
            if ($created) {
                $types = ['support.reply'];
            }
        } elseif ($model instanceof CourseMessage) {
            if ($created) {
                $types = ['message.received'];
            }
            if (! $created && $model->wasChanged('deleted_at') && $model->deleted_at) {
                foreach (PlatformNotification::query()->where('type', 'message.received')->where('target_id', $model->course_conversation_id)->whereNull('read_at')->get() as $notification) {
                    $cursor = CourseReadCursor::query()->where('course_conversation_id', $model->course_conversation_id)->where('user_id', $notification->notifiable_id)->value('last_read_message_id') ?? 0;
                    $remaining = CourseMessage::query()->where('course_conversation_id', $model->course_conversation_id)->where('id', '>', $cursor)->where('user_id', '!=', $notification->notifiable_id)->whereNull('deleted_at');
                    $count = (clone $remaining)->count();
                    $notification->update($count ? ['aggregate_count' => $count, 'target_version' => $remaining->max('id')] : ['read_at' => now()]);
                }
            }
        } elseif ($model instanceof CourseReadCursor) {
            foreach (PlatformNotification::query()->where('notifiable_type', (new User)->getMorphClass())->where('notifiable_id', $model->user_id)->where('type', 'message.received')->where('target_id', $model->course_conversation_id)->whereNull('read_at')->get() as $notification) {
                $remaining = CourseMessage::query()->where('course_conversation_id', $model->course_conversation_id)->where('id', '>', (int) $model->last_read_message_id)->where('user_id', '!=', $model->user_id)->whereNull('deleted_at');
                $count = (clone $remaining)->count();
                $notification->update($count ? ['aggregate_count' => $count, 'target_version' => $remaining->max('id')] : ['read_at' => now()]);
            }
        } elseif ($model instanceof CourseReview) {
            if ($changed && $status === 'pending') {
                $types = ['review.pending'];
            }
        } elseif ($model instanceof LessonVideoUpload) {
            if ($changed && in_array((string) $status, ['failed', 'error', '5'], true)) {
                $types = ['video.failed'];
            }
        } elseif ($model instanceof User) {
            if (! $created && $model->wasChanged(['password', 'email', 'status'])) {
                $types = ['security.changed'];
            }
        } elseif ($model instanceof TransactionalDelivery) {
            if ($changed && $status === 'failed') {
                $types = ['delivery.failed'];
            }
        }
        if ($model instanceof LessonProgress || $model instanceof QuizAttempt || $model instanceof AssignmentSubmission) {
            $enrollment = $model->enrollment()->with(['user', 'course'])->first();
            if ($enrollment?->user && $enrollment?->course && $enrollment->course->certificate_enabled) {
                $result = app(CertificateEligibilityService::class)->evaluate($enrollment->user, $enrollment->course);
                if ($result['academic_eligible']) {
                    event(new PlatformNotificationEvent('certificate.eligible', $enrollment, 'certificate_eligible:'.$enrollment->id.':'.$enrollment->course->certificate_requirements_version));
                }
            }
        }
        foreach ($types as $type) {
            $version = $created ? 'created' : hash('sha256', json_encode([$model->getChanges(), $model->getOriginal('status')]));
            $key = $type.':'.$model::class.':'.$model->id.':'.$version;
            if ($type === 'enrollment.student_joined') {
                $key = 'student_joined:'.$model->enrollment_id;
            }
            if (in_array($type, ['quiz.available', 'assignment.available'], true) && $model->available_from) {
                $key = 'available:'.$model::class.':'.$model->id.':'.$model->available_from->toIso8601String();
            }
            event(new PlatformNotificationEvent($type, $model, $key, $context));
        }
    }
}
