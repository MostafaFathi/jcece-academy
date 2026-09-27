<?php

namespace App\Services;

use App\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignmentSubmissionService
{
    public function __construct(private AssignmentAccessService $access) {}

    public function startDraft(User $user, Assignment $assignment): AssignmentSubmission
    {
        $enrollment = $this->access->requireAvailable($user, $assignment);

        return DB::transaction(function () use ($user, $assignment, $enrollment): AssignmentSubmission {
            $lockedAssignment = Assignment::query()->lockForUpdate()->findOrFail($assignment->id);
            $this->access->requireAvailable($user, $lockedAssignment->load('course'));
            $activeDraft = AssignmentSubmission::query()
                ->whereBelongsTo($lockedAssignment)
                ->whereBelongsTo($user)
                ->where('status', AssignmentSubmissionStatus::Draft)
                ->lockForUpdate()
                ->first();

            if ($activeDraft !== null) {
                return $this->loadSubmission($activeDraft);
            }

            if ($lockedAssignment->due_at?->isPast() && ! $lockedAssignment->allow_late_submissions) {
                throw ValidationException::withMessages(['assignment' => 'The submission deadline has passed.']);
            }

            $attemptCount = AssignmentSubmission::query()
                ->whereBelongsTo($lockedAssignment)
                ->whereBelongsTo($user)
                ->count();

            if ($lockedAssignment->max_attempts !== null && $attemptCount >= $lockedAssignment->max_attempts) {
                throw ValidationException::withMessages(['assignment' => 'The maximum number of submission attempts has been reached.']);
            }

            $submission = $lockedAssignment->submissions()->create([
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'attempt_number' => $attemptCount + 1,
                'status' => AssignmentSubmissionStatus::Draft,
                'active_key' => "assignment:{$lockedAssignment->id}:user:{$user->id}",
                'assignment_title' => $lockedAssignment->title,
                'assignment_instructions' => $lockedAssignment->instructions,
                'maximum_score' => $lockedAssignment->maximum_score,
                'passing_score' => $lockedAssignment->passing_score,
                'submission_type' => $lockedAssignment->submission_type,
                'due_at' => $lockedAssignment->due_at,
                'allow_late_submissions' => $lockedAssignment->allow_late_submissions,
            ]);

            return $this->loadSubmission($submission);
        }, 3);
    }

    /** @param array{text_answer?: ?string} $attributes */
    public function saveDraft(User $user, AssignmentSubmission $submission, array $attributes): AssignmentSubmission
    {
        return DB::transaction(function () use ($user, $submission, $attributes): AssignmentSubmission {
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->authorizeOwner($user, $lockedSubmission);
            $this->ensureDraft($lockedSubmission);
            $this->access->requireAvailable($user, $lockedSubmission->assignment()->with('course')->firstOrFail());
            $lockedSubmission->update($attributes);

            return $this->loadSubmission($lockedSubmission);
        });
    }

    public function submit(User $user, AssignmentSubmission $submission): AssignmentSubmission
    {
        return DB::transaction(function () use ($user, $submission): AssignmentSubmission {
            $lockedSubmission = AssignmentSubmission::query()->lockForUpdate()->findOrFail($submission->id);
            $this->authorizeOwner($user, $lockedSubmission);

            if ($lockedSubmission->status !== AssignmentSubmissionStatus::Draft) {
                return $this->loadSubmission($lockedSubmission);
            }

            $this->access->requireAvailable($user, $lockedSubmission->assignment()->with('course')->firstOrFail());
            $isLate = $lockedSubmission->due_at?->isPast() ?? false;

            if ($isLate && ! $lockedSubmission->allow_late_submissions) {
                throw ValidationException::withMessages(['submission' => 'The submission deadline has passed.']);
            }

            if ($lockedSubmission->submission_type->requiresText() && blank($lockedSubmission->text_answer)) {
                throw ValidationException::withMessages(['text_answer' => 'A text answer is required for this assignment.']);
            }

            if ($lockedSubmission->submission_type->requiresFile() && ! $lockedSubmission->files()->exists()) {
                throw ValidationException::withMessages(['files' => 'At least one file is required for this assignment.']);
            }

            $lockedSubmission->update([
                'status' => AssignmentSubmissionStatus::Submitted,
                'active_key' => null,
                'submitted_at' => now(),
                'is_late' => $isLate,
            ]);

            return $this->loadSubmission($lockedSubmission);
        }, 3);
    }

    /** @throws AuthorizationException */
    public function authorizeOwner(User $user, AssignmentSubmission $submission): void
    {
        if ($submission->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this assignment submission.');
        }
    }

    public function ensureDraft(AssignmentSubmission $submission): void
    {
        if ($submission->status !== AssignmentSubmissionStatus::Draft) {
            throw ValidationException::withMessages(['submission' => 'Only draft submissions may be changed.']);
        }
    }

    public function loadSubmission(AssignmentSubmission $submission): AssignmentSubmission
    {
        return $submission->refresh()->load(['assignment', 'files', 'grader', 'gradingEvents.reviewer']);
    }
}
