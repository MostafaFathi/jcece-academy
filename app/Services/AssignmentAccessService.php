<?php

namespace App\Services;

use App\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\User;

class AssignmentAccessService
{
    public function __construct(private CourseAccessService $courseAccess) {}

    public function isAvailableTo(User $user, Assignment $assignment): bool
    {
        return $assignment->status === AssignmentStatus::Published
            && ($assignment->available_from === null || $assignment->available_from->lte(now()))
            && $this->courseAccess->hasAccess($user, $assignment->course);
    }

    public function requireAvailable(User $user, Assignment $assignment): Enrollment
    {
        abort_unless($assignment->status === AssignmentStatus::Published, 404);
        abort_if($assignment->available_from !== null && $assignment->available_from->isFuture(), 404);

        return $this->courseAccess->requireAccess($user, $assignment->course);
    }
}
