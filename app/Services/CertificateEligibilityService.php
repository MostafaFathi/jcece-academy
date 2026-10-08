<?php

namespace App\Services;

use App\AssignmentStatus;
use App\AssignmentSubmissionStatus;
use App\CourseStatus;
use App\EnrollmentStatus;
use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\QuizAttemptStatus;
use App\QuizStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class CertificateEligibilityService
{
    public function __construct(
        public CourseAccessService $courseAccess,
        public CourseProgressService $courseProgress,
    ) {}

    /** @return array{eligible: bool, academic_eligible: bool, reasons: list<string>, enrollment: ?Enrollment, progress: array{completed_lessons: int, total_lessons: int, progress_percentage: float}, requirements: array<string, mixed>} */
    public function evaluate(User $user, Course $course): array
    {
        $reasons = [];
        $enrollment = $this->courseAccess->enrollmentFor($user, $course);
        $progress = ['completed_lessons' => 0, 'total_lessons' => 0, 'progress_percentage' => 0.0];
        $requiredPercentage = $course->certificate_required_lesson_percentage;
        $examStatus = $course->certificate_final_exam_required ? 'not_completed' : 'not_required';
        $bestExamPercentage = null;
        $requiredAssignmentIds = DB::table('course_certificate_required_assignments')
            ->where('course_id', $course->id)
            ->pluck('assignment_id')->all();
        $completedAssignments = 0;
        $approvalStatus = $course->certificate_admin_approval_required ? 'not_requested' : 'not_required';

        if (! $course->certificate_enabled) {
            $reasons[] = 'certificates_disabled';
        }

        if ($course->status === CourseStatus::Archived || $course->trashed()) {
            $reasons[] = 'course_archived';
        }

        if ($requiredPercentage === null || $course->certificate_requirements_version === 0) {
            $reasons[] = 'certificate_configuration_missing';
        }

        if ($enrollment === null) {
            $reasons[] = 'enrollment_missing';
        } else {
            if ($enrollment->status === EnrollmentStatus::Suspended) {
                $reasons[] = 'enrollment_suspended';
            }

            if (! $this->courseAccess->enrollmentHasAccess($enrollment)) {
                $reasons[] = 'effective_access_missing';
            }

            $summary = $this->courseProgress->summary($enrollment);
            $progress = [
                'completed_lessons' => $summary['completed_lessons'],
                'total_lessons' => $summary['total_lessons'],
                'progress_percentage' => $summary['progress_percentage'],
            ];

            if ($requiredPercentage !== null && BigDecimal::of($requiredPercentage)->isGreaterThan(0)) {
                if ($summary['total_lessons'] === 0) {
                    $reasons[] = 'course_has_no_applicable_lessons';
                } else {
                    $requiredHundredths = (int) (string) BigDecimal::of($requiredPercentage)->multipliedBy(100);
                    if ($requiredHundredths * $summary['total_lessons'] > $summary['completed_lessons'] * 10000) {
                        $reasons[] = 'lessons_incomplete';
                    }
                }
            }

            if ($course->certificate_final_exam_required) {
                $quiz = $course->finalExamQuiz()->first();
                if ($quiz === null || $quiz->course_id !== $course->id || $quiz->status !== QuizStatus::Published) {
                    $examStatus = 'unavailable';
                    $reasons[] = 'final_exam_unavailable';
                } else {
                    $bestExamPercentage = $quiz->attempts()
                        ->where('user_id', $user->id)
                        ->where('enrollment_id', $enrollment->id)
                        ->where('status', QuizAttemptStatus::Submitted)
                        ->whereNotNull('percentage')
                        ->orderByDesc('percentage')
                        ->value('percentage');
                    if ($bestExamPercentage === null) {
                        $reasons[] = 'final_exam_incomplete';
                    } elseif (BigDecimal::of((string) $bestExamPercentage)->isLessThan((string) $course->certificate_final_exam_passing_percentage)) {
                        $examStatus = 'failed';
                        $reasons[] = 'final_exam_failed';
                    } else {
                        $examStatus = 'passed';
                    }
                }
            }

            if ($requiredAssignmentIds !== []) {
                $activeAssignmentIds = $course->assignments()
                    ->whereIn('id', $requiredAssignmentIds)
                    ->where('status', AssignmentStatus::Published)
                    ->pluck('id');
                $completedAssignments = $enrollment->assignmentSubmissions()
                    ->whereIn('assignment_id', $activeAssignmentIds)
                    ->where('user_id', $user->id)
                    ->where('status', AssignmentSubmissionStatus::Graded)
                    ->where('passed', true)
                    ->distinct('assignment_id')
                    ->count('assignment_id');

                if ($completedAssignments < count($requiredAssignmentIds)) {
                    $reasons[] = 'required_assignments_incomplete';
                }
            }
        }

        $academicEligible = $reasons === [];
        if ($course->certificate_admin_approval_required && $course->certificate_requirements_version > 0) {
            $approvalStatus = CertificateApprovalRequest::query()
                ->whereBelongsTo($course)
                ->whereBelongsTo($user)
                ->where('requirements_version', $course->certificate_requirements_version)
                ->value('status') ?? 'not_requested';
            if ($approvalStatus !== CertificateApprovalRequest::Approved) {
                $reasons[] = 'admin_approval_pending';
            }
        }

        return [
            'eligible' => $reasons === [],
            'academic_eligible' => $academicEligible,
            'reasons' => $reasons,
            'enrollment' => $enrollment,
            'progress' => $progress,
            'requirements' => [
                'required_lesson_percentage' => $requiredPercentage,
                'final_exam' => [
                    'required' => $course->certificate_final_exam_required,
                    'passing_percentage' => $course->certificate_final_exam_passing_percentage,
                    'best_percentage' => $bestExamPercentage,
                    'status' => $examStatus,
                ],
                'assignments' => [
                    'required_count' => count($requiredAssignmentIds),
                    'completed_count' => $completedAssignments,
                ],
                'approval' => [
                    'required' => $course->certificate_admin_approval_required,
                    'status' => $approvalStatus,
                ],
            ],
        ];
    }
}
