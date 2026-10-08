<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use App\CourseStatus;
use App\Services\CourseAccessService;
use App\Services\ProtectedVideoPlaybackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProtectedVideoPlaybackController extends Controller
{
    public function __invoke(Request $request, Course $course, Lesson $lesson, CourseAccessService $access, ProtectedVideoPlaybackService $playback): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $access->requireAccess($user, $course);
        abort_unless($course->status === CourseStatus::Published
            && $lesson->type->value === 'video'
            && $lesson->is_published
            && $lesson->section->is_active
            && $lesson->section->course_id === $course->id, 404);

        return response()->json(['data' => $playback->playback($lesson)])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
