<?php

namespace App\Providers;

use App\Contracts\CertificatePdfGenerator;
use App\Contracts\ProtectedVideoProvider;
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
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\TransactionalDelivery;
use App\Models\User;
use App\Observers\NotificationWorkflowObserver;
use App\Services\BunnyProtectedVideoProvider;
use App\Services\MpdfCertificatePdfGenerator;
use App\Services\UnconfiguredProtectedVideoProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CertificatePdfGenerator::class, MpdfCertificatePdfGenerator::class);
        $this->app->bind(ProtectedVideoProvider::class, fn ($app): ProtectedVideoProvider => config('jcec.bunny_stream.enabled')
            ? $app->make(BunnyProtectedVideoProvider::class)
            : $app->make(UnconfiguredProtectedVideoProvider::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Persist inbox/outbox rows inside the originating transaction. External delivery waits for commit.
        foreach ([AuditEvent::class, Course::class, Lesson::class, Quiz::class, QuizAttempt::class, Assignment::class, AssignmentSubmission::class, Enrollment::class, EnrollmentAccessGrant::class, Certificate::class, CertificateApprovalRequest::class, Payment::class, Refund::class, Order::class, SupportTicket::class, SupportTicketMessage::class, CourseMessage::class, CourseReadCursor::class, CourseReview::class, LessonVideoUpload::class, LessonProgress::class, User::class, TransactionalDelivery::class] as $model) {
            $model::observe(NotificationWorkflowObserver::class);
        }
        foreach (['send' => 30, 'reaction' => 60, 'group' => 10, 'private' => 10] as $action => $limit) {
            RateLimiter::for('messaging-'.$action, fn (Request $request) => Limit::perMinute($limit)->by((string) $request->user()?->id));
        }
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => url('/reset-password/'.rawurlencode($token)).'?email='.rawurlencode($user->getEmailForPasswordReset()));
        Model::preventLazyLoading(! app()->isProduction());

        Relation::morphMap([
            'course' => Course::class,
            'package' => Package::class,
        ]);
    }
}
