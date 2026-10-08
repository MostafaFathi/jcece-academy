<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateCourseCertificateRequirementsRequest;
use App\Models\Course;
use App\Services\CourseCertificateRequirementsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CourseCertificateRequirementsController extends Controller
{
    public function show(Course $course): JsonResponse
    {
        Gate::authorize('manageCertificateRequirements', $course);

        return response()->json(['data' => $this->payload($course)]);
    }

    public function update(UpdateCourseCertificateRequirementsRequest $request, Course $course, CourseCertificateRequirementsService $requirements): JsonResponse
    {
        Gate::authorize('manageCertificateRequirements', $course);
        $course = $requirements->update($course, $request->validated(), $request->user());

        return response()->json(['data' => $this->payload($course)]);
    }

    /** @return array<string, mixed> */
    private function payload(Course $course): array
    {
        return [
            'course_id' => $course->id,
            'certificate_enabled' => $course->certificate_enabled,
            'required_lesson_percentage' => $course->certificate_required_lesson_percentage,
            'final_exam_required' => $course->certificate_final_exam_required,
            'final_exam_quiz_id' => $course->certificate_final_exam_quiz_id,
            'final_exam_passing_percentage' => $course->certificate_final_exam_passing_percentage,
            'required_assignment_ids' => DB::table('course_certificate_required_assignments')->where('course_id', $course->id)->pluck('assignment_id')->all(),
            'admin_approval_required' => $course->certificate_admin_approval_required,
            'requirements_version' => $course->certificate_requirements_version,
            'quizzes' => $course->quizzes()->get(['id', 'title', 'status'])->map(fn ($quiz): array => ['id' => $quiz->id, 'title' => $quiz->title, 'status' => $quiz->status->value])->all(),
            'assignments' => $course->assignments()->get(['id', 'title', 'status'])->map(fn ($assignment): array => ['id' => $assignment->id, 'title' => $assignment->title, 'status' => $assignment->status->value])->all(),
        ];
    }
}
