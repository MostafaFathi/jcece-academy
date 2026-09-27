<?php

namespace App\Services;

use App\CourseStatus;
use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;

class CertificateEligibilityService
{
    public function __construct(
        public CourseAccessService $courseAccess,
        public CourseProgressService $courseProgress,
    ) {}

    /** @return array{eligible: bool, reasons: list<string>, enrollment: ?Enrollment, progress: array{completed_lessons: int, total_lessons: int, progress_percentage: float}} */
    public function evaluate(User $user, Course $course): array
    {
        $reasons = [];
        $enrollment = $this->courseAccess->enrollmentFor($user, $course);

        if (! $course->certificate_enabled) {
            $reasons[] = 'certificates_disabled';
        }

        if ($course->status === CourseStatus::Archived || $course->trashed()) {
            $reasons[] = 'course_archived';
        }

        if ($enrollment === null) {
            $reasons[] = 'enrollment_missing';

            return [
                'eligible' => false,
                'reasons' => $reasons,
                'enrollment' => null,
                'progress' => ['completed_lessons' => 0, 'total_lessons' => 0, 'progress_percentage' => 0.0],
            ];
        }

        if ($enrollment->status === EnrollmentStatus::Suspended) {
            $reasons[] = 'enrollment_suspended';
        }

        if (! $this->courseAccess->enrollmentHasAccess($enrollment)) {
            $reasons[] = 'effective_access_missing';
        }

        $summary = $this->courseProgress->summary($enrollment);

        if ($summary['total_lessons'] === 0) {
            $reasons[] = 'course_has_no_applicable_lessons';
        } elseif ($summary['completed_lessons'] !== $summary['total_lessons']) {
            $reasons[] = 'lessons_incomplete';
        }

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'enrollment' => $enrollment,
            'progress' => [
                'completed_lessons' => $summary['completed_lessons'],
                'total_lessons' => $summary['total_lessons'],
                'progress_percentage' => $summary['progress_percentage'],
            ],
        ];
    }
}
