<?php

namespace App\Services;

use App\AssignmentGradingAction;
use App\AssignmentSubmissionStatus;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentGradingService
{
    public function grade(User $reviewer, AssignmentSubmission $submission, string $score, ?string $feedback): AssignmentSubmission
    {
        return $this->recordGrade($reviewer, $submission, $score, $feedback, AssignmentGradingAction::Graded);
    }

    public function correct(User $reviewer, AssignmentSubmission $submission, string $score, ?string $feedback, string $reason): AssignmentSubmission
    {
        return $this->recordGrade($reviewer, $submission, $score, $feedback, AssignmentGradingAction::Corrected, $reason);
    }

    public function requestRevision(User $reviewer, AssignmentSubmission $submission, string $feedback): AssignmentSubmission
    {
        return DB::transaction(function () use ($reviewer, $submission, $feedback): AssignmentSubmission {
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);

            if ($lockedSubmission->status !== AssignmentSubmissionStatus::Submitted) {
                throw ValidationException::withMessages(['submission' => 'Only a submitted assignment may be returned for revision.']);
            }

            $lockedSubmission->gradingEvents()->create([
                'reviewer_id' => $reviewer->id,
                'action' => AssignmentGradingAction::RevisionRequested,
                'feedback' => $feedback,
            ]);
            $lockedSubmission->update([
                'status' => AssignmentSubmissionStatus::RevisionRequested,
                'feedback' => $feedback,
                'graded_by' => $reviewer->id,
                'graded_at' => now(),
            ]);

            return $this->loadSubmission($lockedSubmission);
        });
    }

    private function recordGrade(
        User $reviewer,
        AssignmentSubmission $submission,
        string $score,
        ?string $feedback,
        AssignmentGradingAction $action,
        ?string $reason = null,
    ): AssignmentSubmission {
        return DB::transaction(function () use ($reviewer, $submission, $score, $feedback, $action, $reason): AssignmentSubmission {
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $expectedStatus = $action === AssignmentGradingAction::Corrected
                ? AssignmentSubmissionStatus::Graded
                : AssignmentSubmissionStatus::Submitted;

            if ($lockedSubmission->status !== $expectedStatus) {
                throw ValidationException::withMessages(['submission' => $action === AssignmentGradingAction::Corrected
                    ? 'Only a graded submission may be corrected.'
                    : 'Only a submitted assignment may be graded.']);
            }

            $scoreValue = BigDecimal::of($score);
            $maximumScore = BigDecimal::of($lockedSubmission->maximum_score);

            if ($scoreValue->compareTo(BigDecimal::zero()) < 0 || $scoreValue->compareTo($maximumScore) > 0) {
                throw ValidationException::withMessages(['score' => 'The score must be between zero and the submission maximum score.']);
            }

            $passed = $lockedSubmission->passing_score === null
                ? null
                : $scoreValue->compareTo(BigDecimal::of($lockedSubmission->passing_score)) >= 0;
            $lockedSubmission->gradingEvents()->create([
                'reviewer_id' => $reviewer->id,
                'action' => $action,
                'previous_score' => $lockedSubmission->score,
                'previous_passed' => $lockedSubmission->passed,
                'previous_feedback' => $lockedSubmission->feedback,
                'score' => (string) $scoreValue,
                'passed' => $passed,
                'feedback' => $feedback,
                'reason' => $reason,
            ]);
            $lockedSubmission->update([
                'status' => AssignmentSubmissionStatus::Graded,
                'score' => (string) $scoreValue,
                'passed' => $passed,
                'feedback' => $feedback,
                'graded_by' => $reviewer->id,
                'graded_at' => now(),
            ]);

            return $this->loadSubmission($lockedSubmission);
        });
    }

    private function loadSubmission(AssignmentSubmission $submission): AssignmentSubmission
    {
        return $submission->refresh()->load(['assignment.course', 'user', 'files', 'grader', 'gradingEvents.reviewer']);
    }
}
