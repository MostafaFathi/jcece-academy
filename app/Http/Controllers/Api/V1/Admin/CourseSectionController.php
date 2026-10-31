<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCourseSectionRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCourseSectionRequest;
use App\Http\Resources\Api\V1\CourseSectionResource;
use App\Models\Course;
use App\Models\CourseSection;
use App\Services\CurriculumDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseSectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CourseSection::class);
        Gate::authorize('view', $course);

        return CourseSectionResource::collection($course->sections()->with('lessons.resources')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseSectionRequest $request, Course $course): JsonResponse
    {
        Gate::authorize('create', [CourseSection::class, $course]);

        $section = $course->sections()->create($request->validated());

        return (new CourseSectionResource($section))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course, CourseSection $section): CourseSectionResource
    {
        Gate::authorize('view', $section);
        abort_unless($section->course_id === $course->id, 404);

        return new CourseSectionResource($section->load('lessons.resources'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseSectionRequest $request, Course $course, CourseSection $section): CourseSectionResource
    {
        Gate::authorize('update', $section);

        $section->update($request->validated());

        return new CourseSectionResource($section->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course, CourseSection $section, CurriculumDeletionService $deletion): Response|JsonResponse
    {
        abort_unless($section->course_id === $course->id, 404);
        Gate::authorize('delete', $section);
        $failedVideoCount = $deletion->deleteSection($section);

        if ($failedVideoCount > 0) {
            return response()->json(['warning' => ['code' => 'bunny_cleanup_failed', 'failed_video_count' => $failedVideoCount]]);
        }

        return response()->noContent();
    }
}
