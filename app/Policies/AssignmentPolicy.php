<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\User;
use App\PermissionName;
use App\RoleName;

class AssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AssignmentsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Assignment $assignment): bool
    {
        return $user->can(PermissionName::AssignmentsView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::AssignmentsCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Assignment $assignment): bool
    {
        return $user->can(PermissionName::AssignmentsUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Assignment $assignment): bool
    {
        return $user->can(PermissionName::AssignmentsDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Assignment $assignment): bool
    {
        return $user->can(PermissionName::AssignmentsUpdate->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Assignment $assignment): bool
    {
        return false;
    }

    public function publish(User $user, Assignment $assignment): bool
    {
        return $user->can(PermissionName::AssignmentsPublish->value);
    }

    public function reviewSubmissions(User $user, Assignment $assignment): bool
    {
        if (! $user->can(PermissionName::AssignmentSubmissionsView->value)) {
            return false;
        }

        if ($user->hasAnyRole([RoleName::Admin->value, RoleName::ContentManager->value])) {
            return true;
        }

        return $user->hasRole(RoleName::Instructor->value)
            && $assignment->course()->where('instructor_id', $user->id)->exists();
    }

    public function downloadAttachment(User $user, Assignment $assignment): bool
    {
        return $this->view($user, $assignment) || $this->reviewSubmissions($user, $assignment);
    }
}
