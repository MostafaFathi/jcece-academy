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
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class LessonVideoUploadController extends Controller
{
    public function index(Request $request, Lesson $lesson, BunnyStreamClient $bunny): JsonResponse
    {
        $this->authorizeLesson($request, $lesson);
        $upload = $lesson->videoUploads()->where('status', '!=', 'deleted')->latest('id')->first();

        return response()->json(['data' => $upload ? [
            'id' => $upload->id,
            'status' => $upload->status,
            'encode_progress' => $upload->encode_progress,
            'filename' => $upload->filename,
            'size_bytes' => $upload->size_bytes,
            'is_current' => $upload->is_current,
        ] : null, 'meta' => [
            'configured' => $bunny->configured(),
            'max_upload_megabytes' => (int) config('jcec.bunny_stream.max_upload_megabytes'),
            'cleanup_pending' => $lesson->videoUploads()->where('status', 'cleanup_failed')->get(['id', 'filename'])->toArray(),
        ]])->header('Cache-Control', 'no-store, private');
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
            throw ValidationException::withMessages(['filename' => 'The video file extension and type do not match.']);
        }

        $reservation = DB::transaction(function () use ($lesson, $request, $input): array {
            Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();
            $existing = $lesson->videoUploads()->where('request_id', $input['request_id'])->first();

            if ($existing) {
                abort_unless($existing->uploaded_by === $request->user()->id
                    && $existing->filename === $input['filename']
                    && $existing->mime_type === $input['mime_type']
                    && $existing->size_bytes === (int) $input['size_bytes'], 409);

                return ['upload' => $existing, 'created' => false];
            }

            abort_if($lesson->videoUploads()->whereIn('status', ['creating', 'uploading', 'processing', 'uncertain', 'failed', 'retired', 'cleanup_failed'])->exists(), 409);

            $upload = $lesson->videoUploads()->create([
                'uploaded_by' => $request->user()->id,
                'request_id' => $input['request_id'],
                'filename' => $input['filename'],
                'mime_type' => $input['mime_type'],
                'size_bytes' => $input['size_bytes'],
                'status' => 'creating',
            ]);

            return ['upload' => $upload, 'created' => true];
        });
        $upload = $reservation['upload'];

        if ($reservation['created']) {
            try {
                $guid = $bunny->create($lesson->title);
                $upload->update(['video_guid' => $guid, 'status' => 'uploading']);
            } catch (\Throwable $exception) {
                $upload->update(['status' => 'uncertain']);
                throw new ServiceUnavailableHttpException(null, 'Video upload creation is uncertain; contact an administrator before retrying.');
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
                0, 6 => 'uploading',
                default => throw new ServiceUnavailableHttpException(null, 'Unsupported video processing state.'),
            };

            $retired = null;
            DB::transaction(function () use ($upload, $lesson, $state, $nextStatus, &$retired): void {
                $currentLesson = Lesson::query()->whereKey($lesson->id)->lockForUpdate()->firstOrFail();
                $locked = LessonVideoUpload::query()->lockForUpdate()->findOrFail($upload->id);
                if (! in_array($locked->status, ['uploading', 'processing'], true)) {
                    return;
                }

                $locked->update([
                    'status' => $nextStatus,
                    'encode_progress' => $state['encode_progress'],
                    'uploaded_at' => in_array($nextStatus, ['processing', 'ready'], true) ? ($locked->uploaded_at ?? now()) : $locked->uploaded_at,
                ]);

                if ($nextStatus === 'ready') {
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
        abort_if(in_array($upload->status, ['creating', 'uncertain'], true), 409, 'Upload creation must be reconciled before removal.');

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
