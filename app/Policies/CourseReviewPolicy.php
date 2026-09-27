<?php

namespace App\Policies;

use App\Models\CourseReview;
use App\Models\User;
use App\PermissionName;

class CourseReviewPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::ReviewsView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CourseReview $courseReview): bool
    {
        return $courseReview->user_id === $user->id
            || $user->can(PermissionName::ReviewsView->value);
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
    public function update(User $user, CourseReview $courseReview): bool
    {
        return $courseReview->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CourseReview $courseReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CourseReview $courseReview): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CourseReview $courseReview): bool
    {
        return false;
    }

    public function moderate(User $user, CourseReview $courseReview): bool
    {
        return $user->can(PermissionName::ReviewsModerate->value);
    }
}
