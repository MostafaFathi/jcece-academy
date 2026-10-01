<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\PermissionName;

class CoursePolicy
{
    public function selectInstructor(User $user): bool
    {
        return $user->can(PermissionName::CoursesCreate->value)
            || $user->can(PermissionName::CoursesUpdate->value);
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CoursesView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CoursesCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesUpdate->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesDelete->value);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesPublish->value);
    }
}
