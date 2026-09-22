<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\UpdateLessonProgressRequest;
use App\Http\Resources\Api\V1\StudentLessonProgressResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\CourseProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function update(
        UpdateLessonProgressRequest $request,
        Course $course,
        Lesson $lesson,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $courseAccessService->requireAccess($user, $course);
        $progress = $courseProgressService->updateLessonProgress($enrollment, $lesson, $request->validated());

        return (new StudentLessonProgressResource($progress))->response()->setStatusCode(200);
    }

    public function complete(
        Request $request,
        Course $course,
        Lesson $lesson,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): JsonResponse {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $courseAccessService->requireAccess($user, $course);
        $progress = $courseProgressService->completeLesson($enrollment, $lesson);

        return (new StudentLessonProgressResource($progress))->response()->setStatusCode(200);
    }
}
