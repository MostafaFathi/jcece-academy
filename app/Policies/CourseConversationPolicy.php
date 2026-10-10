<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\User;
use App\Services\CourseAccessService;
use App\UserStatus;

class CourseConversationPolicy
{
    public function __construct(private CourseAccessService $access) {}

    public function eligible(User $user, Course $course): bool
    {
        if ($user->status !== UserStatus::Active || $course->trashed() || ! $user->can('messaging.view')) {
            return false;
        }
        if ($course->instructor_id === $user->id) {
            return $user->hasRole('instructor') && $user->can('courses.view');
        }

        return $this->access->hasAccess($user, $course);
    }

    public function view(User $user, CourseConversation $conversation): bool
    {
        $course = $conversation->course;
        if ($course === null || $course->instructor_id !== $conversation->instructor_id || ! $this->eligible($user, $course)) {
            return false;
        }
        if ($user->id === $conversation->instructor_id) {
            return true;
        }

        return match ($conversation->kind) {
            'private' => $conversation->student_id === $user->id,
            'all' => true,
            'selected' => $conversation->members()->where('user_id', $user->id)->whereNull('removed_at')->exists(),
            default => false,
        };
    }

    public function send(User $user, CourseConversation $conversation): bool
    {
        return $this->view($user, $conversation) && $user->can('messaging.send');
    }

    public function manage(User $user, CourseConversation $conversation): bool
    {
        return $conversation->kind !== 'private' && $user->id === $conversation->instructor_id
            && $this->view($user, $conversation) && $user->can('messaging.groups.manage');
    }
}
