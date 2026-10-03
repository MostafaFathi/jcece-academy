<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UploadLessonResourceRequest;
use App\Http\Resources\Api\V1\LessonResourceResource;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Services\LessonResourceUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LessonResourceUploadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UploadLessonResourceRequest $request, Lesson $lesson, LessonResourceUploadService $uploads): JsonResponse
    {
        Gate::authorize('create', [LessonResource::class, $lesson]);
        $resource = $uploads->store($lesson, $request->file('file'), $request->string('title')->toString(), $request->boolean('is_downloadable', true));

        return (new LessonResourceResource($resource))->response()->setStatusCode(201);
    }
}
