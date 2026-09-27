<?php

namespace App\Services;

use App\CourseReviewStatus;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseReviewSubmissionService
{
    public function __construct(public CourseAccessService $courseAccess) {}

    /** @param array{rating: int, title?: ?string, body?: ?string} $attributes */
    public function create(User $user, Course $course, array $attributes): CourseReview
    {
        $enrollment = $this->courseAccess->requireAccess($user, $course);

        return DB::transaction(function () use ($user, $course, $enrollment, $attributes): CourseReview {
            $lockedEnrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);

            if (! $this->courseAccess->enrollmentHasAccess($lockedEnrollment)) {
                throw new AuthorizationException('You do not currently have access to this course.');
            }

            if (CourseReview::withTrashed()->whereBelongsTo($user)->whereBelongsTo($course)->exists()) {
                throw ValidationException::withMessages([
                    'course' => 'You already have a review for this course.',
                ]);
            }

            $review = CourseReview::query()->create([
                'course_id' => $course->id,
                'user_id' => $user->id,
                'rating' => $attributes['rating'],
                'title' => $attributes['title'] ?? null,
                'body' => $attributes['body'] ?? null,
                'status' => CourseReviewStatus::Pending,
                'submitted_at' => now(),
            ]);
            $this->recordHistory($review, $user, 'submitted', null, CourseReviewStatus::Pending, null, $review);

            return $review->refresh();
        });
    }

    /** @param array{rating?: int, title?: ?string, body?: ?string} $attributes */
    public function update(User $user, CourseReview $review, array $attributes): CourseReview
    {
        if ($review->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $review->loadMissing('course');
        $enrollment = $this->courseAccess->requireAccess($user, $review->course);

        return DB::transaction(function () use ($user, $review, $enrollment, $attributes): CourseReview {
            $lockedEnrollment = Enrollment::query()->lockForUpdate()->findOrFail($enrollment->id);
            $lockedReview = CourseReview::query()->lockForUpdate()->findOrFail($review->id);

            if (! $this->courseAccess->enrollmentHasAccess($lockedEnrollment)) {
                throw new AuthorizationException('You do not currently have access to this course.');
            }

            $oldReview = $lockedReview->replicate();
            $oldReview->status = $lockedReview->status;
            $lockedReview->fill($attributes);
            $lockedReview->status = CourseReviewStatus::Pending;
            $lockedReview->submitted_at = now();
            $lockedReview->published_at = null;
            $lockedReview->moderated_by = null;
            $lockedReview->moderated_at = null;
            $lockedReview->moderation_reason = null;
            $lockedReview->save();
            $this->recordHistory(
                $lockedReview,
                $user,
                'resubmitted',
                $oldReview->status,
                CourseReviewStatus::Pending,
                $oldReview,
                $lockedReview,
            );

            return $lockedReview->refresh();
        });
    }

    private function recordHistory(
        CourseReview $review,
        User $actor,
        string $action,
        ?CourseReviewStatus $fromStatus,
        CourseReviewStatus $toStatus,
        ?CourseReview $oldReview,
        CourseReview $newReview,
    ): void {
        $review->histories()->create([
            'actor_id' => $actor->id,
            'actor_name_snapshot' => $actor->name,
            'action' => $action,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'old_rating' => $oldReview?->rating,
            'new_rating' => $newReview->rating,
            'old_title' => $oldReview?->title,
            'new_title' => $newReview->title,
            'old_body' => $oldReview?->body,
            'new_body' => $newReview->body,
            'created_at' => now(),
        ]);
    }
}
