<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonVideoUpload;
use App\PermissionName;
use App\Services\BunnyStreamClient;
use App\Services\ProtectedVideoPlaybackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class LessonVideoUploadController extends Controller
{
    public function index(Request $request, Lesson $lesson): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $upload = $lesson->videoUploads()->latest('id')->first();

        return response()->json(['data' => $upload ? [
            'id' => $upload->id,
            'status' => $upload->status,
            'encode_progress' => $upload->encode_progress,
            'filename' => $upload->filename,
            'size_bytes' => $upload->size_bytes,
            'is_current' => $upload->is_current,
        ] : null])->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Lesson $lesson, BunnyStreamClient $bunny): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        abort_unless($lesson->type->value === 'video' && ! $lesson->is_preview, 422);
        $bunny->requireConfigured();

        $input = $request->validate([
            'request_id' => ['required', 'uuid'],
            'filename' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/'],
            'mime_type' => ['required', Rule::in(['video/mp4', 'video/quicktime', 'video/webm'])],
            'size_bytes' => ['required', 'integer', 'min:1', 'max:'.((int) config('jcec.bunny_stream.max_upload_megabytes') * 1048576)],
        ]);

        $extension = strtolower(pathinfo($input['filename'], PATHINFO_EXTENSION));
        $expectedMime = ['mp4' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm'][$extension] ?? null;
        if ($expectedMime !== $input['mime_type']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['filename' => 'The video file extension and type do not match.']);
        }

        $upload = DB::transaction(function () use ($lesson, $request, $input): LessonVideoUpload {
            Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $existing = $lesson->videoUploads()->where('request_id', $input['request_id'])->first();

            if ($existing) {
                abort_unless($existing->uploaded_by === $request->user()->id
                    && $existing->filename === $input['filename']
                    && $existing->mime_type === $input['mime_type']
                    && $existing->size_bytes === (int) $input['size_bytes'], 409);

                return $existing;
            }

            abort_if($lesson->videoUploads()->whereIn('status', ['creating', 'uploading', 'processing', 'uncertain'])->exists(), 409);

            return $lesson->videoUploads()->create([
                'uploaded_by' => $request->user()->id,
                'request_id' => $input['request_id'],
                'filename' => $input['filename'],
                'mime_type' => $input['mime_type'],
                'size_bytes' => $input['size_bytes'],
                'status' => 'creating',
            ]);
        });

        if ($upload->status === 'creating' && $upload->video_guid === null) {
            try {
                $guid = $bunny->create($lesson->title);
                $upload->update(['video_guid' => $guid, 'status' => 'uploading']);
            } catch (\Throwable $exception) {
                $upload->update(['status' => 'uncertain']);
                throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'Video upload creation is uncertain; contact an administrator before retrying.');
            }
        }

        abort_unless($upload->status === 'uploading' && filled($upload->video_guid), 409);

        return response()->json(['data' => [
            'id' => $upload->id,
            'status' => $upload->status,
            'upload' => $bunny->uploadAuthorization($upload->video_guid),
        ]], 201)->header('Cache-Control', 'no-store, private');
    }

    public function show(Request $request, Lesson $lesson, LessonVideoUpload $videoUpload, BunnyStreamClient $bunny): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $upload = $videoUpload;
        abort_unless($upload->lesson_id === $lesson->id, 404);

        if (in_array($upload->status, ['uploading', 'processing'], true)) {
            $state = $bunny->status($upload->video_guid);
            $nextStatus = match ($state['status']) {
                3 => 'ready',
                5, 8 => 'failed',
                1, 2, 4, 7 => 'processing',
                default => 'uploading',
            };

            $retired = null;
            DB::transaction(function () use ($upload, $lesson, $state, $nextStatus, &$retired): void {
                $locked = LessonVideoUpload::query()->lockForUpdate()->findOrFail($upload->id);
                if (! in_array($locked->status, ['uploading', 'processing'], true)) {
                    return;
                }

                $locked->update(['status' => $nextStatus, 'encode_progress' => $state['encode_progress']]);

                if ($nextStatus === 'ready') {
                    $currentLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();
                    $retired = $currentLesson->videoUploads()->where('is_current', true)->where('id', '!=', $locked->id)->first();
                    if ($retired) {
                        $retired->update(['status' => 'retired', 'is_current' => false]);
                    }
                    $locked->update(['is_current' => true, 'ready_at' => now()]);
                    $currentLesson->update([
                        'video_provider' => 'bunny_stream',
                        'video_id' => null,
                        'video_url' => null,
                        'protected_video_asset_key' => $locked->video_guid,
                        'duration_seconds' => $state['length'] > 0 ? $state['length'] : $currentLesson->duration_seconds,
                    ]);
                }
            });

            if ($retired) {
                $this->deleteRemote($retired, $bunny);
            }
        }

        return $this->statusResponse($upload->refresh());
    }

    public function destroy(Request $request, Lesson $lesson, LessonVideoUpload $videoUpload, BunnyStreamClient $bunny): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $upload = $videoUpload;
        abort_unless($upload->lesson_id === $lesson->id, 404);

        DB::transaction(function () use ($lesson, $upload): void {
            $currentLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $locked = LessonVideoUpload::query()->lockForUpdate()->findOrFail($upload->id);
            if ($currentLesson->protected_video_asset_key === $locked->video_guid && $locked->is_current) {
                $currentLesson->update(['protected_video_asset_key' => null, 'video_provider' => null]);
            }
            if ($locked->status !== 'deleted') {
                $locked->update(['status' => 'retired', 'is_current' => false]);
            }
        });

        if ($upload->video_guid && $upload->status !== 'deleted') {
            $this->deleteRemote($upload, $bunny);
        }

        return $this->statusResponse($upload->refresh());
    }

    public function preview(Request $request, Lesson $lesson, ProtectedVideoPlaybackService $playback): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        abort_unless($lesson->type->value === 'video' && $lesson->video_provider === 'bunny_stream', 404);

        return response()->json(['data' => $playback->playback($lesson)])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }

    private function authorizeLesson(Request $request, Lesson $lesson): void
    {
        Gate::authorize('update', $lesson);
        $course = $lesson->section->course;
        abort_unless($course->instructor_id === $request->user()->id
            || $request->user()->can(PermissionName::CoursesPublish->value), 403);
    }

    private function deleteRemote(LessonVideoUpload $upload, BunnyStreamClient $bunny): void
    {
        try {
            $bunny->delete($upload->video_guid);
            $upload->update(['status' => 'deleted', 'deleted_at' => now()]);
        } catch (\Throwable) {
            $upload->update(['status' => 'cleanup_failed']);
        }
    }

    private function statusResponse(LessonVideoUpload $upload): JsonResponse
    {
        return response()->json(['data' => [
            'id' => $upload->id,
            'status' => $upload->status,
            'encode_progress' => $upload->encode_progress,
            'filename' => $upload->filename,
            'size_bytes' => $upload->size_bytes,
            'is_current' => $upload->is_current,
        ]])->header('Cache-Control', 'no-store, private');
    }
}
