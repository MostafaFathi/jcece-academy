<?php

namespace App\Policies;

use App\Models\AssignmentSubmission;
use App\Models\User;
use App\PermissionName;
use App\RoleName;

class AssignmentSubmissionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AssignmentSubmissionsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $assignmentSubmission->user_id === $user->id || $this->canReview($user, $assignmentSubmission);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $assignmentSubmission->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return false;
    }

    public function grade(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $user->can(PermissionName::AssignmentSubmissionsGrade->value)
            && $this->canReview($user, $assignmentSubmission);
    }

    public function review(User $user, AssignmentSubmission $assignmentSubmission): bool
    {
        return $this->canReview($user, $assignmentSubmission);
    }

    private function canReview(User $user, AssignmentSubmission $submission): bool
    {
        if (! $user->can(PermissionName::AssignmentSubmissionsView->value)) {
            return false;
        }

        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::ContentManager->value])) {
            return true;
        }

        $submission->loadMissing('assignment.course');

        return $user->hasRole(RoleName::Instructor->value)
            && $submission->assignment->course->instructor_id === $user->id;
    }
}
