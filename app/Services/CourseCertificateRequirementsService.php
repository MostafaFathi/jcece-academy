<?php

namespace App\Services;

use App\Models\Course;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class CourseCertificateRequirementsService
{
    /** @param array<string, mixed> $attributes */
    public function update(Course $course, array $attributes, User $actor): Course
    {
        return DB::transaction(function () use ($course, $attributes, $actor): Course {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
            $assignmentIds = collect($attributes['required_assignment_ids'])->map(fn ($id): int => (int) $id)->sort()->values()->all();
            $previousAssignmentIds = DB::table('course_certificate_required_assignments')->where('course_id', $course->id)->pluck('assignment_id')->sort()->values()->all();
            $finalExamRequired = (bool) $attributes['final_exam_required'];
            $settings = [
                'certificate_enabled' => (bool) $attributes['certificate_enabled'],
                'certificate_required_lesson_percentage' => (string) BigDecimal::of((string) $attributes['required_lesson_percentage'])->toScale(2),
                'certificate_final_exam_required' => $finalExamRequired,
                'certificate_final_exam_quiz_id' => $finalExamRequired ? (int) $attributes['final_exam_quiz_id'] : null,
                'certificate_final_exam_passing_percentage' => $finalExamRequired ? (string) BigDecimal::of((string) $attributes['final_exam_passing_percentage'])->toScale(2) : null,
                'certificate_admin_approval_required' => (bool) $attributes['admin_approval_required'],
            ];
            $changed = $assignmentIds !== $previousAssignmentIds;

            foreach ($settings as $key => $value) {
                if ($course->{$key} !== $value) {
                    $changed = true;
                }
            }

            if ($changed) {
                $course->forceFill($settings + [
                    'certificate_requirements_version' => $course->certificate_requirements_version + 1,
                    'updated_by' => $actor->id,
                ])->save();
                $course->requiredCertificateAssignments()->sync($assignmentIds);
            }

            return $course->refresh();
        });
    }
}
