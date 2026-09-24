<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\User;
use App\QuizStatus;

class QuizAccessService
{
    public function __construct(private CourseAccessService $courseAccess) {}

    public function isAvailableTo(User $user, Quiz $quiz): bool
    {
        return $quiz->status === QuizStatus::Published
            && ($quiz->available_from === null || $quiz->available_from->lte(now()))
            && ($quiz->available_until === null || $quiz->available_until->gte(now()))
            && $this->courseAccess->hasAccess($user, $quiz->course);
    }

    public function requireAvailable(User $user, Quiz $quiz): Enrollment
    {
        abort_unless($quiz->status === QuizStatus::Published, 404);
        abort_if($quiz->available_from !== null && $quiz->available_from->isFuture(), 404);
        abort_if($quiz->available_until !== null && $quiz->available_until->isPast(), 404);

        return $this->courseAccess->requireAccess($user, $quiz->course);
    }
}
