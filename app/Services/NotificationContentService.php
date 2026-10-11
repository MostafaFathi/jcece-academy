<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditEvent;
use App\Models\Certificate;
use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonVideoUpload;
use App\Models\NotificationDelivery;
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
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class NotificationContentService
{
    /** @return array<string, string> */
    public function snapshot(Model $subject): array
    {
        if ($subject instanceof EnrollmentAccessGrant) {
            $subject->loadMissing('enrollment');
            $snapshot = $subject->enrollment ? $this->snapshot($subject->enrollment) : [];
            $snapshot['deadline'] = $subject->access_expires_at?->toIso8601String() ?? '';

            return $snapshot;
        }

        if ($subject instanceof SupportTicketMessage) {
            $subject->loadMissing('ticket');

            return $subject->ticket ? $this->snapshot($subject->ticket) : [];
        }

        if ($subject instanceof CourseMessage) {
            $subject->loadMissing('conversation');

            return $subject->conversation ? $this->snapshot($subject->conversation) : [];
        }

        if ($subject instanceof LessonVideoUpload) {
            $subject->loadMissing('lesson');

            return $subject->lesson ? $this->snapshot($subject->lesson) : [];
        }

        $relations = match (true) {
            $subject instanceof Enrollment => ['course', 'user', 'accessGrants'],
            $subject instanceof Lesson => ['section.course'],
            $subject instanceof AssignmentSubmission => ['assignment.course', 'user'],
            $subject instanceof QuizAttempt => ['quiz.course'],
            $subject instanceof Assignment, $subject instanceof Quiz, $subject instanceof Certificate,
            $subject instanceof CertificateApprovalRequest, $subject instanceof CourseConversation,
            $subject instanceof CourseReview => ['course'],
            $subject instanceof Payment, $subject instanceof Refund => ['order'],
            default => [],
        };
        if ($relations !== []) {
            $subject->loadMissing($relations);
        }

        $course = match (true) {
            $subject instanceof Course => $subject,
            $subject instanceof Lesson => $subject->section?->course,
            $subject instanceof AssignmentSubmission => $subject->assignment?->course,
            $subject instanceof QuizAttempt => $subject->quiz?->course,
            $subject instanceof Enrollment, $subject instanceof Assignment, $subject instanceof Quiz,
            $subject instanceof Certificate, $subject instanceof CertificateApprovalRequest,
            $subject instanceof CourseConversation, $subject instanceof CourseReview => $subject->course,
            default => null,
        };

        $name = match (true) {
            $subject instanceof AssignmentSubmission => $subject->assignment_title ?: $subject->assignment?->title,
            $subject instanceof QuizAttempt => $subject->quiz?->title,
            $subject instanceof Certificate => $subject->course_title_snapshot ?: $course?->title,
            $subject instanceof Announcement => $subject->title['ar'] ?? $subject->title['en'] ?? null,
            $subject instanceof SupportTicket => $subject->subject,
            $subject instanceof Order => $subject->order_number,
            $subject instanceof Payment, $subject instanceof Refund => $subject->order?->order_number,
            $subject instanceof User => $subject->name,
            default => $subject->title ?? null,
        };

        $snapshot = [];
        if (is_string($name) && $name !== '') {
            $snapshot['name'] = $name;
        }
        if ($course && $course !== $subject && $course->title) {
            $snapshot['course'] = $course->title;
        }

        $deadline = match (true) {
            $subject instanceof Assignment => $subject->due_at,
            $subject instanceof AssignmentSubmission => $subject->due_at,
            $subject instanceof Quiz => $subject->available_until,
            $subject instanceof QuizAttempt => $subject->quiz?->available_until,
            default => null,
        };
        if ($deadline instanceof CarbonInterface) {
            $snapshot['deadline'] = $deadline->toIso8601String();
        }

        if ($subject instanceof SupportTicket) {
            $snapshot['number'] = $subject->ticket_number;
        } elseif ($subject instanceof Certificate && $subject->certificate_number) {
            $snapshot['number'] = $subject->certificate_number;
        }

        if ($subject instanceof Order || $subject instanceof Payment || $subject instanceof Refund) {
            $snapshot['amount'] = (string) ($subject instanceof Order ? $subject->total : $subject->amount);
            $snapshot['currency'] = (string) $subject->currency;
        }

        if ($subject instanceof QuizAttempt && $subject->show_results && $subject->score !== null) {
            $snapshot['score'] = (string) $subject->score;
            $snapshot['maximum_score'] = (string) $subject->maximum_score;
        }
        if ($subject instanceof AssignmentSubmission && $subject->score !== null) {
            $snapshot['score'] = (string) $subject->score;
            $snapshot['maximum_score'] = (string) $subject->maximum_score;
        }
        if ($subject instanceof Announcement) {
            $snapshot['name_ar'] = $subject->title['ar'] ?? '';
            $snapshot['name_en'] = $subject->title['en'] ?? '';
        }
        if ($subject instanceof Enrollment) {
            $latestExpiry = $subject->accessGrants->whereNull('revoked_at')->pluck('access_expires_at')->filter()->max();
            if ($latestExpiry instanceof CarbonInterface) {
                $snapshot['deadline'] = $latestExpiry->toIso8601String();
            }
        }
        if ($subject instanceof Enrollment && $subject->user?->name) {
            $snapshot['student'] = $subject->user->name;
        }
        if ($subject instanceof AssignmentSubmission && $subject->user?->name) {
            $snapshot['student'] = $subject->user->name;
        }
        if ($subject instanceof Order || $subject instanceof Refund || $subject instanceof SupportTicket) {
            $snapshot['status'] = (string) ($subject->status instanceof \BackedEnum ? $subject->status->value : $subject->status);
        }
        if ($subject instanceof NotificationDelivery || $subject instanceof TransactionalDelivery) {
            $snapshot['failed_type'] = (string) ($subject instanceof NotificationDelivery ? $subject->type : $subject->message_type);
        }
        if ($subject instanceof AuditEvent && is_string($subject->metadata['role'] ?? null)) {
            $snapshot['role'] = $subject->metadata['role'];
        }

        return array_filter($snapshot, fn ($value): bool => $value !== null && $value !== '');
    }

    /** @return array{title: string, description: string} */
    public function present(string $type, array $snapshot, string $locale, int $count = 1): array
    {
        $language = $locale === 'en' ? 'en' : 'ar';
        $definition = config('platform_notifications.types')[$type] ?? null;
        $base = $definition[$language] ?? $type;
        $name = $type === 'announcement.published'
            ? ($snapshot['name_'.$language] ?? $snapshot['name'] ?? '')
            : ($snapshot['name'] ?? $snapshot['course'] ?? $snapshot['role'] ?? $snapshot['failed_type'] ?? '');
        $title = $name !== '' ? $base.': '.$name : $base;
        $details = [];

        if (! empty($snapshot['course']) && $snapshot['course'] !== $name) {
            $details[] = ($language === 'ar' ? 'الدورة: ' : 'Course: ').$snapshot['course'];
        }
        if (! empty($snapshot['number']) && $snapshot['number'] !== $name) {
            $details[] = ($language === 'ar' ? 'الرقم: ' : 'Number: ').$snapshot['number'];
        }
        if (! empty($snapshot['deadline']) && (str_starts_with($type, 'assignment.') || str_starts_with($type, 'quiz.') || $type === 'enrollment.expiring')) {
            $label = match (true) {
                str_starts_with($type, 'assignment.') => $language === 'ar' ? 'آخر موعد للتسليم: ' : 'Due: ',
                str_starts_with($type, 'quiz.') => $language === 'ar' ? 'يغلق في: ' : 'Closes: ',
                default => $language === 'ar' ? 'ينتهي الوصول في: ' : 'Access ends: ',
            };
            $details[] = $label.Carbon::parse($snapshot['deadline'])->timezone(config('platform_notifications.timezone'))->format('Y-m-d H:i');
        }
        if (isset($snapshot['score'], $snapshot['maximum_score']) && in_array($type, ['quiz.result', 'assignment.graded'], true)) {
            $details[] = ($language === 'ar' ? 'النتيجة: ' : 'Score: ').$snapshot['score'].' / '.$snapshot['maximum_score'];
        }
        if (isset($snapshot['amount'], $snapshot['currency'])) {
            $details[] = ($language === 'ar' ? 'المبلغ: ' : 'Amount: ').$snapshot['amount'].' '.$snapshot['currency'];
        }
        if (! empty($snapshot['student']) && in_array($type, ['enrollment.student_joined', 'assignment.submitted'], true)) {
            $details[] = ($language === 'ar' ? 'الطالب: ' : 'Student: ').$snapshot['student'];
        }
        if (! empty($snapshot['status']) && in_array($type, ['order.updated', 'refund.updated', 'support.updated'], true)) {
            $statuses = ['pending' => ['ar' => 'قيد الانتظار', 'en' => 'Pending'], 'paid' => ['ar' => 'مدفوع', 'en' => 'Paid'], 'approved' => ['ar' => 'معتمد', 'en' => 'Approved'], 'rejected' => ['ar' => 'مرفوض', 'en' => 'Rejected'], 'refunded' => ['ar' => 'مسترد', 'en' => 'Refunded'], 'processing' => ['ar' => 'قيد المعالجة', 'en' => 'Processing'], 'completed' => ['ar' => 'مكتمل', 'en' => 'Completed'], 'open' => ['ar' => 'مفتوحة', 'en' => 'Open'], 'resolved' => ['ar' => 'محلولة', 'en' => 'Resolved'], 'closed' => ['ar' => 'مغلقة', 'en' => 'Closed']];
            $details[] = ($language === 'ar' ? 'الحالة: ' : 'Status: ').($statuses[$snapshot['status']][$language] ?? $snapshot['status']);
        }
        if (! empty($snapshot['failed_type']) && $snapshot['failed_type'] !== $name) {
            $details[] = ($language === 'ar' ? 'نوع الإشعار: ' : 'Notification type: ').$snapshot['failed_type'];
        }
        if (! empty($snapshot['role']) && $snapshot['role'] !== $name && $type === 'security.permissions_changed') {
            $details[] = ($language === 'ar' ? 'الدور: ' : 'Role: ').$snapshot['role'];
        }
        if ($type === 'system.critical' && $details === []) {
            $details[] = $language === 'ar' ? 'تعذّر تنفيذ مهمة خلفية؛ يرجى مراجعة سجل المهام.' : 'A background job failed; review the job log.';
        }
        if ($count > 1) {
            $details[] = ($language === 'ar' ? 'عدد الرسائل: ' : 'Messages: ').$count;
        }

        return ['title' => $title, 'description' => implode(' · ', $details)];
    }

    /** @param Collection<int, PlatformNotification> $notifications
     * @return array<string, array<string, string>>
     */
    public function legacySnapshots(Collection $notifications): array
    {
        $models = [
            'course' => Course::class, 'enrollment' => Enrollment::class, 'lesson' => Lesson::class,
            'quiz' => Quiz::class, 'quiz_attempt' => QuizAttempt::class, 'assignment' => Assignment::class,
            'submission' => AssignmentSubmission::class, 'order' => Order::class, 'payment' => Payment::class,
            'refund' => Refund::class, 'certificate' => Certificate::class,
            'certificate_request' => CertificateApprovalRequest::class, 'ticket' => SupportTicket::class,
            'conversation' => CourseConversation::class, 'review' => CourseReview::class,
            'announcement' => Announcement::class, 'user' => User::class,
        ];
        $relations = [
            'enrollment' => ['course', 'user', 'accessGrants'], 'lesson' => ['section.course'],
            'quiz' => ['course'], 'quiz_attempt' => ['quiz.course'],
            'assignment' => ['course'], 'submission' => ['assignment.course', 'user'],
            'payment' => ['order'], 'refund' => ['order'], 'certificate' => ['course'],
            'certificate_request' => ['course'], 'conversation' => ['course'], 'review' => ['course'],
        ];
        $snapshots = [];
        foreach ($notifications->filter(fn (PlatformNotification $notification): bool => ! isset($notification->data['context']))->groupBy('target_type') as $targetType => $group) {
            $class = $models[$targetType] ?? null;
            if (! $class) {
                continue;
            }
            $query = $class::query();
            if (isset($relations[$targetType])) {
                $query->with($relations[$targetType]);
            }
            if (in_array($class, [Course::class, Lesson::class, Quiz::class, Assignment::class, CourseReview::class], true)) {
                $query->withTrashed();
            }
            foreach ($query->whereKey($group->pluck('target_id')->unique()->all())->get() as $model) {
                $snapshots[$targetType.':'.$model->getKey()] = $this->snapshot($model);
            }
        }

        return $snapshots;
    }
}
