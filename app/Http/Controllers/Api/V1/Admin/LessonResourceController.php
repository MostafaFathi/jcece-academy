<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreLessonResourceRequest;
use App\Http\Requests\Api\V1\Admin\UpdateLessonResourceRequest;
use App\Http\Resources\Api\V1\LessonResourceResource;
use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LessonResourceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Lesson $lesson): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LessonResource::class);

        return LessonResourceResource::collection($lesson->resources()->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLessonResourceRequest $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('create', [LessonResource::class, $lesson]);

        $resource = $lesson->resources()->create($request->validated());

        return (new LessonResourceResource($resource))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Lesson $lesson, LessonResource $resource): LessonResourceResource
    {
        Gate::authorize('view', $resource);

        return new LessonResourceResource($resource);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLessonResourceRequest $request, Lesson $lesson, LessonResource $resource): LessonResourceResource
    {
        Gate::authorize('update', $resource);
        $resource->update($request->validated());

        return new LessonResourceResource($resource->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Lesson $lesson, LessonResource $resource): Response
    {
        Gate::authorize('delete', $resource);
        $resource->delete();

        return response()->noContent();
    }
}
