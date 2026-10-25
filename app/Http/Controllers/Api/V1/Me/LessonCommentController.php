<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreLessonCommentRequest;
use App\Http\Resources\Api\V1\LessonCommentResource;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonComment;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Services\CourseAccessService;
use App\Services\UploadedFileStorage;
use App\UserStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LessonCommentController extends Controller
{
    public function index(Request $request, Course $course, Lesson $lesson, CourseAccessService $courseAccessService): JsonResponse
    {
        $this->authorizeDiscussion($request->user(), $course, $lesson, $courseAccessService);
        $filters = $request->validate(['before' => ['sometimes', 'integer', 'min:1']]);
        $comments = $lesson->comments()->whereNull('parent_id')
            ->with(['user:id,name', 'replies' => fn ($query) => $query->with('user:id,name')->orderBy('id')->limit(3)])
            ->withCount('replies')
            ->when(isset($filters['before']), fn ($query) => $query->where('id', '<', $filters['before']))
            ->orderByDesc('id')->limit(11)->get();
        $hasMore = $comments->count() > 10;
        $page = $comments->take(10);

        return response()->json([
            'data' => LessonCommentResource::collection($page)->resolve(),
            'next_before' => $hasMore ? $page->last()?->id : null,
        ]);
    }

    public function replies(Request $request, Course $course, Lesson $lesson, LessonComment $comment, CourseAccessService $courseAccessService): JsonResponse
    {
        $this->authorizeDiscussion($request->user(), $course, $lesson, $courseAccessService);
        abort_unless($comment->parent_id === null, 404);
        $filters = $request->validate(['after' => ['sometimes', 'integer', 'min:1']]);
        $replies = $comment->replies()->with('user:id,name')
            ->when(isset($filters['after']), fn ($query) => $query->where('id', '>', $filters['after']))
            ->orderBy('id')->limit(11)->get();
        $hasMore = $replies->count() > 10;
        $page = $replies->take(10);

        return response()->json([
            'data' => LessonCommentResource::collection($page)->resolve(),
            'next_after' => $hasMore ? $page->last()?->id : null,
        ]);
    }

    public function store(StoreLessonCommentRequest $request, Course $course, Lesson $lesson, CourseAccessService $courseAccessService, UploadedFileStorage $uploads): JsonResponse
    {
        $this->authorizeDiscussion($request->user(), $course, $lesson, $courseAccessService);

        $parentId = $request->integer('parent_id') ?: null;

        if ($parentId !== null) {
            $parent = $lesson->comments()->find($parentId);

            if ($parent === null) {
                throw ValidationException::withMessages(['parent_id' => 'The reply target is not available in this lesson.']);
            }

            $depth = 0;

            while ($parent->parent_id !== null && $depth < 20) {
                $parent = $lesson->comments()->find($parent->parent_id);

                if ($parent === null) {
                    throw ValidationException::withMessages(['parent_id' => 'The reply target is not available in this lesson.']);
                }

                $depth++;
            }

            if ($parent->parent_id !== null) {
                throw ValidationException::withMessages(['parent_id' => 'The reply target is not available in this lesson.']);
            }

            $parentId = $parent->id;
        }

        $file = $request->file('attachment');
        $path = null;
        $attachmentName = $file ? Str::limit((string) preg_replace('/[[:cntrl:]]/u', '', str_replace(['/', '\\'], '', $file->getClientOriginalName())), 180, '') : null;

        if ($file && in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/webp'], true) && @getimagesize($file->getPathname()) === false) {
            throw ValidationException::withMessages(['attachment' => 'Upload a valid image.']);
        }

        try {
            if ($file) {
                $path = $uploads->store($file, 'lesson-comments', 'local', 'attachment');
            }

            $comment = $lesson->comments()->create([
                'user_id' => $request->user()->id,
                'parent_id' => $parentId,
                'body' => trim($request->string('body')->toString()) ?: null,
                'attachment_path' => $path,
                'attachment_name' => $attachmentName,
                'attachment_mime' => $file?->getMimeType(),
                'attachment_size' => $file?->getSize(),
            ]);
        } catch (Throwable $exception) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $comment->load('user:id,name');

        return (new LessonCommentResource($comment))->response()->setStatusCode(201);
    }

    public function attachment(Request $request, Course $course, Lesson $lesson, LessonComment $comment, CourseAccessService $courseAccessService): StreamedResponse
    {
        $this->authorizeDiscussion($request->user(), $course, $lesson, $courseAccessService);
        abort_unless($comment->attachment_path && Storage::disk('local')->exists($comment->attachment_path), 404);

        $headers = [
            'Content-Type' => $comment->attachment_mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];

        if (in_array($comment->attachment_mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return Storage::disk('local')->response($comment->attachment_path, $comment->attachment_name, $headers, 'inline');
        }

        return Storage::disk('local')->download($comment->attachment_path, $comment->attachment_name, $headers);
    }

    private function authorizeDiscussion(User $user, Course $course, Lesson $lesson, CourseAccessService $courseAccessService): void
    {
        abort_unless($lesson->is_published && $lesson->section->is_active, 404);
        abort_unless($user->status === UserStatus::Active && $course->discussion_enabled, 403);

        if ($course->instructor_id === $user->id && $user->hasRole(RoleName::Instructor->value) && $user->can(PermissionName::CoursesView->value) && $user->can(PermissionName::CurriculumView->value)) {
            return;
        }

        $courseAccessService->requireAccess($user, $course);
    }
}
