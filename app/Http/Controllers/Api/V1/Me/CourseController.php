<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StudentCourseProgressResource;
use App\Http\Resources\Api\V1\StudentEnrollmentResource;
use App\Http\Resources\Api\V1\StudentLearningCourseResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\CourseProgressService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    public function index(
        Request $request,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): AnonymousResourceCollection {
        /** @var User $user */
        $user = $request->user();
        $enrollments = $user->enrollments()
            ->whereHas('course')
            ->with(['course', 'currentAccessGrants'])
            ->latest('enrolled_at')
            ->latest('id')
            ->paginate(15);
        $progressSummaries = $courseProgressService->summaries($enrollments->getCollection());

        $enrollments->through(fn (Enrollment $enrollment): array => [
            'enrollment' => $enrollment,
            'access' => $courseAccessService->accessMetadata($enrollment),
            'progress' => $progressSummaries[$enrollment->id],
        ]);

        return StudentEnrollmentResource::collection($enrollments);
    }

    public function show(
        Request $request,
        Course $course,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): StudentEnrollmentResource {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $courseAccessService->requireAccess($user, $course);
        $enrollment->setRelation('course', $course);

        return new StudentEnrollmentResource([
            'enrollment' => $enrollment,
            'access' => $courseAccessService->accessMetadata($enrollment),
            'progress' => $courseProgressService->summary($enrollment),
        ]);
    }

    public function learn(
        Request $request,
        Course $course,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): StudentLearningCourseResource {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $courseAccessService->requireAccess($user, $course);

        $course->load([
            'sections' => fn ($query) => $query->where('is_active', true),
            'sections.lessons' => fn ($query) => $query->where('is_published', true),
            'sections.lessons.resources',
            'sections.lessons.progressRecords' => fn ($query) => $query->whereBelongsTo($enrollment),
        ]);

        return new StudentLearningCourseResource([
            'course' => $course,
            'enrollment' => $enrollment,
            'access' => $courseAccessService->accessMetadata($enrollment),
            'progress' => $courseProgressService->summary($enrollment),
        ]);
    }

    public function progress(
        Request $request,
        Course $course,
        CourseAccessService $courseAccessService,
        CourseProgressService $courseProgressService,
    ): StudentCourseProgressResource {
        /** @var User $user */
        $user = $request->user();
        $enrollment = $courseAccessService->requireAccess($user, $course);
        $enrollment->setRelation('course', $course);
        $lessonProgress = $enrollment->lessonProgress()
            ->whereHas('lesson', fn (Builder $query) => $query
                ->where('is_published', true)
                ->whereHas('section', fn (Builder $query) => $query
                    ->whereBelongsTo($course)
                    ->where('is_active', true)))
            ->get();

        return new StudentCourseProgressResource([
            'enrollment' => $enrollment,
            'access' => $courseAccessService->accessMetadata($enrollment),
            'progress' => $courseProgressService->summary($enrollment),
            'lesson_progress' => $lessonProgress,
        ]);
    }
}
