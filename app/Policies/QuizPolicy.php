<?php

namespace App\Policies;

use App\Models\Quiz;
use App\Models\User;
use App\PermissionName;
use App\RoleName;

class QuizPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AssessmentsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentsView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::AssessmentsCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentsUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentsDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentsUpdate->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Quiz $quiz): bool
    {
        return false;
    }

    public function publish(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentsPublish->value);
    }

    public function viewResults(User $user, Quiz $quiz): bool
    {
        return $user->can(PermissionName::AssessmentResultsView->value);
    }

    public function viewInstructorResults(User $user, Quiz $quiz): bool
    {
        return $user->hasRole(RoleName::Instructor->value)
            && $user->can(PermissionName::AssignmentSubmissionsView->value)
            && $quiz->course()->where('instructor_id', $user->id)->exists();
    }
}
