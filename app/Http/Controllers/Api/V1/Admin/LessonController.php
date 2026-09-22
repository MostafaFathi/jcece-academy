<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreLessonRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLessonRequest;
use App\Http\Resources\Api\V1\LessonResource as LessonApiResource;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LessonController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(CourseSection $section): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Lesson::class);

        return LessonApiResource::collection($section->lessons()->with('resources')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLessonRequest $request, CourseSection $section): JsonResponse
    {
        Gate::authorize('create', [Lesson::class, $section]);

        $lesson = $section->lessons()->create($request->validated());

        return (new LessonApiResource($lesson))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(CourseSection $section, Lesson $lesson): LessonApiResource
    {
        Gate::authorize('view', $lesson);

        return new LessonApiResource($lesson->load('resources'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLessonRequest $request, CourseSection $section, Lesson $lesson): LessonApiResource
    {
        Gate::authorize('update', $lesson);
        $lesson->update($request->validated());

        return new LessonApiResource($lesson->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CourseSection $section, Lesson $lesson): Response
    {
        Gate::authorize('delete', $lesson);
        $lesson->delete();

        return response()->noContent();
    }
}
