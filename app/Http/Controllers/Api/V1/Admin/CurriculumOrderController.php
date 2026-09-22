<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderCurriculumRequest;
use App\Http\Resources\Api\V1\CourseSectionResource;
use App\Http\Resources\Api\V1\LessonResource as LessonApiResource;
use App\Http\Resources\Api\V1\LessonResourceResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Services\CurriculumOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CurriculumOrderController extends Controller
{
    public function sections(ReorderCurriculumRequest $request, Course $course, CurriculumOrderService $orderService): AnonymousResourceCollection
    {
        Gate::authorize('reorder', [CourseSection::class, $course]);
        $orderService->reorderSections($course, $request->validated('ids'));

        return CourseSectionResource::collection($course->sections()->get());
    }

    public function lessons(ReorderCurriculumRequest $request, CourseSection $section, CurriculumOrderService $orderService): AnonymousResourceCollection
    {
        Gate::authorize('reorder', [Lesson::class, $section]);
        $orderService->reorderLessons($section, $request->validated('ids'));

        return LessonApiResource::collection($section->lessons()->get());
    }

    public function resources(ReorderCurriculumRequest $request, Lesson $lesson, CurriculumOrderService $orderService): AnonymousResourceCollection
    {
        Gate::authorize('reorder', [LessonResource::class, $lesson]);
        $orderService->reorderResources($lesson, $request->validated('ids'));

        return LessonResourceResource::collection($lesson->resources()->get());
    }
}
