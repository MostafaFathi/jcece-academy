<?php

namespace App\Services;

use App\CourseReviewStatus;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CourseReviewModerationService
{
    public function publish(CourseReview $review, User $moderator): CourseReview
    {
        $action = $review->status === CourseReviewStatus::Hidden ? 'restored' : 'published';

        return $this->transition($review, $moderator, CourseReviewStatus::Published, $action);
    }

    public function reject(CourseReview $review, User $moderator, string $reason): CourseReview
    {
        return $this->transition($review, $moderator, CourseReviewStatus::Rejected, 'rejected', $reason);
    }

    public function hide(CourseReview $review, User $moderator, string $reason): CourseReview
    {
        return $this->transition($review, $moderator, CourseReviewStatus::Hidden, 'hidden', $reason);
    }

    private function transition(
        CourseReview $review,
        User $moderator,
        CourseReviewStatus $toStatus,
        string $action,
        ?string $reason = null,
    ): CourseReview {
        return DB::transaction(function () use ($review, $moderator, $toStatus, $action, $reason): CourseReview {
            $lockedReview = CourseReview::query()->lockForUpdate()->findOrFail($review->id);
            $allowedTransitions = [
                CourseReviewStatus::Pending->value => [CourseReviewStatus::Published, CourseReviewStatus::Rejected],
                CourseReviewStatus::Published->value => [CourseReviewStatus::Hidden],
                CourseReviewStatus::Hidden->value => [CourseReviewStatus::Published],
                CourseReviewStatus::Rejected->value => [],
            ];

            if (! in_array($toStatus, $allowedTransitions[$lockedReview->status->value], true)) {
                throw ValidationException::withMessages([
                    'status' => "A {$lockedReview->status->value} review cannot transition to {$toStatus->value}.",
                ]);
            }

            $fromStatus = $lockedReview->status;
            $lockedReview->status = $toStatus;
            $lockedReview->published_at = $toStatus === CourseReviewStatus::Published ? now() : null;
            $lockedReview->moderated_by = $moderator->id;
            $lockedReview->moderated_at = now();
            $lockedReview->moderation_reason = $reason;
            $lockedReview->save();
            $lockedReview->histories()->create([
                'actor_id' => $moderator->id,
                'actor_name_snapshot' => $moderator->name,
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'old_rating' => $lockedReview->rating,
                'new_rating' => $lockedReview->rating,
                'old_title' => $lockedReview->title,
                'new_title' => $lockedReview->title,
                'old_body' => $lockedReview->body,
                'new_body' => $lockedReview->body,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            return $lockedReview->refresh();
        });
    }
}
