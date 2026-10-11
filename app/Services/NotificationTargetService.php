<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonVideoUpload;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class NotificationTargetService
{
    public function target(Model $subject): array
    {
        return match (true) {
            $subject instanceof SupportTicketMessage => ['ticket', $subject->support_ticket_id],
            $subject instanceof CourseMessage => ['conversation', $subject->course_conversation_id],
            $subject instanceof LessonVideoUpload => ['lesson', $subject->lesson_id],
            default => [match ($subject::class) {
                Course::class => 'course',Enrollment::class => 'enrollment',Lesson::class => 'lesson',Quiz::class => 'quiz',QuizAttempt::class => 'quiz_attempt',Assignment::class => 'assignment',AssignmentSubmission::class => 'submission',Order::class => 'order',Payment::class => 'payment',Refund::class => 'refund',Certificate::class => 'certificate',CertificateApprovalRequest::class => 'certificate_request',SupportTicket::class => 'ticket',CourseConversation::class => 'conversation',CourseReview::class => 'review',Announcement::class => 'announcement',User::class => 'user',default => 'system'
            }, $subject->getKey()]
        };
    }

    public function course(Model $subject): ?Course
    {
        if ($subject instanceof Course) {
            return $subject;
        }
        if ($subject instanceof Lesson) {
            return $subject->section()->first()?->course()->first();
        }
        if ($subject instanceof LessonVideoUpload) {
            $lesson = $subject->lesson()->first();

            return $lesson ? $this->course($lesson) : null;
        }
        if ($subject instanceof AssignmentSubmission) {
            return $subject->assignment()->first()?->course()->first();
        }
        if ($subject instanceof QuizAttempt) {
            return $subject->quiz()->first()?->course()->first();
        }

        return method_exists($subject, 'course') ? $subject->course()->first() : null;
    }

    public function path(User $user, string $type, int $id): ?string
    {
        $class = match ($type) {
            'course' => Course::class,'enrollment' => Enrollment::class,'lesson' => Lesson::class,'quiz' => Quiz::class,'quiz_attempt' => QuizAttempt::class,'assignment' => Assignment::class,'submission' => AssignmentSubmission::class,'order' => Order::class,'payment' => Payment::class,'refund' => Refund::class,'certificate' => Certificate::class,'certificate_request' => CertificateApprovalRequest::class,'ticket' => SupportTicket::class,'conversation' => CourseConversation::class,'review' => CourseReview::class,'announcement' => Announcement::class,'user' => User::class,default => null
        };
        if (! $class) {
            return $user->can('notifications.manage') ? '/notification-management' : null;
        }
        $model = $class::query()->find($id);
        if (! $model) {
            return null;
        }
        $staff = $user->can('courses.update');
        $course = $this->course($model);
        if ($type === 'announcement') {
            return app(AnnouncementService::class)->canRead($user, $model) ? '/announcements/'.$id : null;
        }
        if ($type === 'user') {
            return $user->id === $id ? '/profile' : null;
        }
        if ($type === 'conversation') {
            $model->load('course');

            return Gate::forUser($user)->allows('view', $model) ? ($user->hasRole('instructor') ? '/instructor/messages' : '/student/messages').'?conversation='.$id : null;
        }
        if ($type === 'enrollment') {
            return $model->user_id === $user->id ? '/student/courses' : null;
        }
        if (in_array($type, ['course', 'lesson', 'quiz', 'assignment'], true)) {
            if (! $course || $course->trashed()) {
                return null;
            }
            if ($staff && Gate::forUser($user)->allows('view', $course)) {
                if ($type === 'lesson' && ! Gate::forUser($user)->allows('viewCurriculum', $course)) {
                    return null;
                }
                if ($user->hasRole('instructor') && ! $user->hasAnyRole(['admin', 'content_manager'])) {
                    return '/instructor/courses/'.$course->id;
                }

                return '/admin/courses/'.$course->id.($type === 'lesson' ? '/curriculum' : '/edit');
            }
            if (! app(CourseAccessService::class)->hasAccess($user, $course)) {
                return null;
            }
            if ($type === 'quiz') {
                return app(QuizAccessService::class)->isAvailableTo($user, $model) ? '/student/quizzes/'.$id : null;
            }
            if ($type === 'assignment') {
                return app(AssignmentAccessService::class)->isAvailableTo($user, $model) ? '/student/assignments/'.$id : null;
            }
            if ($type === 'lesson' && ! $model->is_published) {
                return null;
            }

            return '/learn/courses/'.rawurlencode($course->slug).($type === 'lesson' ? '?lesson='.$id : '');
        }
        if ($type === 'quiz_attempt') {
            return $model->user_id === $user->id && $model->show_results && $course && app(CourseAccessService::class)->hasAccess($user, $course) ? '/student/quizzes/'.$model->quiz_id : null;
        }
        if ($type === 'submission') {
            if ($model->user_id === $user->id) {
                return $course && app(CourseAccessService::class)->hasAccess($user, $course) ? '/student/assignments/'.$model->assignment_id : null;
            }
            if (! Gate::forUser($user)->allows('review', $model) || ! $course) {
                return null;
            }

            return $user->hasRole('instructor') && ! $user->hasAnyRole(['admin', 'content_manager']) ? '/instructor/courses/'.$course->id.'/assignments/'.$model->assignment_id.'/submissions/'.$id : '/admin/courses/'.$course->id.'/assessments';
        }
        if ($type === 'certificate_request') {
            return $model->user_id === $user->id ? '/student/certificates' : ($user->can('certificates.issue') && $user->can('certificates.view') ? '/admin/certificates' : null);
        }
        if ($type === 'refund') {
            $order = $model->order()->first();

            return $order ? $this->path($user, 'order', $order->id) : null;
        }
        if (! Gate::forUser($user)->allows('view', $model)) {
            return null;
        }
        $area = $user->hasRole('sales_support') && ! $user->hasRole('admin') ? 'sales-support' : 'admin';

        return match ($type) {
            'ticket' => $model->user_id === $user->id ? '/student/support/'.$id : ($area === 'sales-support' ? '/sales-support/tickets/' : '/admin/support/').$id,
            'order' => $model->user_id === $user->id ? '/student/orders/'.$id : '/'.$area.'/orders/'.$id,
            'payment' => $model->order()->where('user_id', $user->id)->exists() ? '/student/orders/'.$model->order_id : '/'.$area.'/payments/'.$id,
            'certificate' => $model->user_id === $user->id ? '/student/certificates' : '/admin/certificates/'.$id,
            'review' => $user->can('reviews.view') ? '/admin/reviews/'.$id : null,
            default => null,
        };
    }
}
