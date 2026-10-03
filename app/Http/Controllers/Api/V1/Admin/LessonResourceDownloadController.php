<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Services\LessonResourceFileService;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonResourceDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Lesson $lesson, LessonResource $resource, LessonResourceFileService $files): StreamedResponse
    {
        abort_unless($resource->lesson_id === $lesson->id, 404);
        Gate::authorize('view', $resource);

        return $files->download($resource, false);
    }
}
