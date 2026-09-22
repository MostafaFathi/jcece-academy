<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\User;
use App\PermissionName;

class CourseSectionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CurriculumView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, CourseSection $courseSection): bool
    {
        return $user->can(PermissionName::CurriculumView->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumCreate->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, CourseSection $courseSection): bool
    {
        return $user->can(PermissionName::CurriculumUpdate->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, CourseSection $courseSection): bool
    {
        return $user->can(PermissionName::CurriculumDelete->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, CourseSection $courseSection): bool
    {
        return $user->can(PermissionName::CurriculumUpdate->value);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, CourseSection $courseSection): bool
    {
        return $user->can(PermissionName::CurriculumDelete->value);
    }

    public function reorder(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumUpdate->value);
    }
}
