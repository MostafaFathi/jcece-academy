<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use App\PermissionName;

class LessonResourcePolicy
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
    public function view(User $user, LessonResource $lessonResource): bool
    {
        return $user->can(PermissionName::CurriculumView->value)
            && $user->can('viewCurriculum', $lessonResource->lesson->section->course);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Lesson $lesson): bool
    {
        return $user->can('createCurriculum', $lesson->section->course);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LessonResource $lessonResource): bool
    {
        return $user->can('updateCurriculum', $lessonResource->lesson->section->course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, LessonResource $lessonResource): bool
    {
        return $user->can('deleteCurriculum', $lessonResource->lesson->section->course);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, LessonResource $lessonResource): bool
    {
        return $this->update($user, $lessonResource);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, LessonResource $lessonResource): bool
    {
        return $this->delete($user, $lessonResource);
    }

    public function reorder(User $user, Lesson $lesson): bool
    {
        return $user->can('updateCurriculum', $lesson->section->course);
    }
}
