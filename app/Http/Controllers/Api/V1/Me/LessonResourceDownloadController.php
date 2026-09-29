<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\LessonResourceFileService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonResourceDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        Course $course,
        Lesson $lesson,
        LessonResource $resource,
        CourseAccessService $courseAccessService,
        LessonResourceFileService $files,
    ): StreamedResponse {
        /** @var User $user */
        $user = $request->user();
        $courseAccessService->requireAccess($user, $course);

        abort_unless($resource->lesson_id === $lesson->id && $lesson->is_published
            && $lesson->section->course_id === $course->id && $lesson->section->is_active, 404);

        return $files->download($resource);
    }
}
