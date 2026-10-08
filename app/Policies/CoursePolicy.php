<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;
use App\PermissionName;
use App\RoleName;

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
        return $user->can(PermissionName::CoursesView->value)
            && $this->inScope($user, $course);
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
        return $user->can(PermissionName::CoursesUpdate->value)
            && $this->inScope($user, $course);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesDelete->value) && $this->inScope($user, $course);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Course $course): bool
    {
        return $this->update($user, $course);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        return $this->delete($user, $course);
    }

    public function publish(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CoursesPublish->value) && $this->inScope($user, $course);
    }

    public function viewCurriculum(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumView->value) && $this->view($user, $course);
    }

    public function createCurriculum(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumCreate->value) && $this->viewCurriculum($user, $course);
    }

    public function updateCurriculum(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumUpdate->value) && $this->viewCurriculum($user, $course);
    }

    public function deleteCurriculum(User $user, Course $course): bool
    {
        return $user->can(PermissionName::CurriculumDelete->value) && $this->viewCurriculum($user, $course);
    }

    public function manageCertificateRequirements(User $user, Course $course): bool
    {
        return $this->update($user, $course)
            && $user->hasAnyRole([RoleName::Admin->value, RoleName::ContentManager->value]);
    }

    public function assignInstructor(User $user, int $instructorId): bool
    {
        return ($user->can(PermissionName::CoursesCreate->value) || $user->can(PermissionName::CoursesUpdate->value))
            && (! $this->isInstructorOnly($user) || $instructorId === $user->id);
    }

    private function isInstructorOnly(User $user): bool
    {
        return $user->hasRole(RoleName::Instructor->value)
            && ! $user->hasAnyRole([RoleName::ContentManager->value, RoleName::Admin->value]);
    }

    private function inScope(User $user, Course $course): bool
    {
        return ! $this->isInstructorOnly($user) || $course->instructor_id === $user->id;
    }
}
