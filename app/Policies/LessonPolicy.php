<?php

namespace App\Policies;

use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use App\PermissionName;

class LessonPolicy
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
    public function view(User $user, Lesson $lesson): bool
    {
        return $user->can(PermissionName::CurriculumView->value)
            && $user->can('viewCurriculum', $lesson->section->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, CourseSection $section): bool
    {
        return $user->can('createCurriculum', $section->course);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        return $user->can('updateCurriculum', $lesson->section->course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->can('deleteCurriculum', $lesson->section->course);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Lesson $lesson): bool
    {
        return $this->delete($user, $lesson);
    }

    public function reorder(User $user, CourseSection $section): bool
    {
        return $user->can('updateCurriculum', $section->course);
    }
}
