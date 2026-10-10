<?php

namespace App\Services;

use App\Events\CourseConversationChanged;
use App\Models\Course;
use App\Models\CourseConversation;
use App\Models\CourseMessage;
use App\Models\CourseMessageAttachment;
use App\Models\CourseReadCursor;
use App\Models\User;
use App\Policies\CourseConversationPolicy;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Throwable;

class CourseMessagingService
{
    public function __construct(private CourseConversationPolicy $policy, private UploadedFileStorage $files) {}

    public function conversation(User $user, int $id): CourseConversation
    {
        $conversation = CourseConversation::query()->with('course')->findOrFail($id);
        abort_unless($this->policy->view($user, $conversation), 404);

        return $conversation;
    }

    public function visibleQuery(User $user): Builder
    {
        $ids = Course::query()->where(function (Builder $query) use ($user): void {
            $query->where('instructor_id', $user->id)->orWhereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('user_id', $user->id));
        })->get()->filter(fn (Course $course): bool => $this->policy->eligible($user, $course))->pluck('id');

        return CourseConversation::query()->with('course')
            ->whereIn('course_id', $ids)
            ->whereHas('course', fn (Builder $courses) => $courses->whereColumn('courses.instructor_id', 'course_conversations.instructor_id'))
            ->where(function (Builder $query) use ($user): void {
                $query->where('instructor_id', $user->id)
                    ->orWhere(fn (Builder $private) => $private->where('kind', 'private')->where('student_id', $user->id))
                    ->orWhere('kind', 'all')
                    ->orWhere(fn (Builder $selected) => $selected->where('kind', 'selected')->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->id)->whereNull('removed_at')));
            });
    }

    public function privateConversation(User $actor, Course $course, ?int $studentId): CourseConversation
    {
        abort_unless($this->policy->eligible($actor, $course) && $actor->can('messaging.send'), 403);

        return DB::transaction(function () use ($actor, $course, $studentId): CourseConversation {
            $lockedCourse = Course::query()->lockForUpdate()->findOrFail($course->id);
            abort_unless($this->policy->eligible($actor, $lockedCourse), 403);
            if ($actor->id !== $lockedCourse->instructor_id) {
                abort_if($studentId !== null && $studentId !== $actor->id, 403);
                $studentId = $actor->id;
            }
            $student = User::query()->find($studentId);
            abort_unless($student && $student->id !== $lockedCourse->instructor_id && $this->policy->eligible($student, $lockedCourse), 422);
            $instructor = User::query()->find($lockedCourse->instructor_id);
            abort_unless($instructor && $this->policy->eligible($instructor, $lockedCourse), 422);

            return CourseConversation::query()->firstOrCreate([
                'course_id' => $lockedCourse->id, 'instructor_id' => $lockedCourse->instructor_id, 'student_id' => $student->id,
            ], ['kind' => 'private'])->load('course');
        });
    }

    /** @param array<int, int> $ids */
    public function validateMembers(Course $course, array $ids): void
    {
        foreach ($ids as $id) {
            $student = User::query()->find($id);
            if (! $student || $id === $course->instructor_id || ! $this->policy->eligible($student, $course)) {
                throw ValidationException::withMessages(['student_ids' => 'Every selected student must have current course access and messaging permission.']);
            }
        }
    }

    public function manageCourse(User $actor, Course $course): void
    {
        abort_unless($course->instructor_id === $actor->id && $this->policy->eligible($actor, $course) && $actor->can('messaging.groups.manage'), 403);
    }

    /** @param array<int, int> $ids */
    public function group(User $actor, Course $course, string $title, string $kind, array $ids): CourseConversation
    {
        return DB::transaction(function () use ($actor, $course, $title, $kind, $ids): CourseConversation {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            $this->manageCourse($actor, $course);
            $this->validateMembers($course, $ids);
            $conversation = CourseConversation::query()->create(['course_id' => $course->id, 'instructor_id' => $actor->id, 'kind' => $kind, 'title' => $title]);
            $conversation->setRelation('course', $course);
            if ($kind === 'selected') {
                foreach ($ids as $id) {
                    $conversation->members()->create(['user_id' => $id]);
                }
            }
            $this->event($conversation, 'members');

            return $conversation;
        });
    }

    /** @param array<int, int> $ids */
    public function members(User $actor, CourseConversation $conversation, array $ids): CourseConversation
    {
        return DB::transaction(function () use ($actor, $conversation, $ids): CourseConversation {
            $course = Course::query()->lockForUpdate()->findOrFail($conversation->course_id);
            $locked = $this->conversation($actor, $conversation->id);
            $locked = CourseConversation::query()->lockForUpdate()->findOrFail($locked->id);
            $locked->setRelation('course', $course);
            abort_unless($this->policy->manage($actor, $locked) && $locked->kind === 'selected', 403);
            $this->validateMembers($course, $ids);
            $locked->members()->whereNotIn('user_id', $ids)->whereNull('removed_at')->update(['removed_at' => now()]);
            foreach ($ids as $id) {
                $locked->members()->updateOrCreate(['user_id' => $id], ['removed_at' => null]);
            }
            $this->event($locked, 'members');

            return $locked;
        });
    }

    /** @return array{kind: string, mime: string, duration: ?float} */
    private function media(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?? '';
        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            if ($file->getSize() > 5 * 1024 * 1024 || @getimagesize($file->getPathname()) === false) {
                throw ValidationException::withMessages(['attachment' => 'Upload a valid image no larger than 5 MB.']);
            }

            return ['kind' => 'image', 'mime' => $mime, 'duration' => null];
        }
        if (! in_array($mime, ['audio/webm', 'video/webm', 'audio/ogg', 'application/ogg', 'audio/mp4', 'video/mp4', 'audio/x-m4a', 'audio/wav', 'audio/x-wav'], true)) {
            throw ValidationException::withMessages(['attachment' => 'Only JPEG, PNG, WebP and supported voice files are allowed.']);
        }
        if (config('messaging.voice_validation') === 'portable') {
            $header = file_get_contents($file->getPathname(), false, null, 0, 12);
            $hasExpectedSignature = match ($mime) {
                'audio/webm', 'video/webm' => str_starts_with($header ?: '', "\x1A\x45\xDF\xA3"),
                'audio/ogg', 'application/ogg' => str_starts_with($header ?: '', 'OggS'),
                'audio/mp4', 'video/mp4', 'audio/x-m4a' => substr($header ?: '', 4, 4) === 'ftyp',
                'audio/wav', 'audio/x-wav' => str_starts_with($header ?: '', 'RIFF') && substr($header ?: '', 8, 4) === 'WAVE',
                default => false,
            };
            if (! $hasExpectedSignature) {
                throw ValidationException::withMessages(['attachment' => 'Upload a supported voice file no larger than 10 MB.']);
            }

            return ['kind' => 'voice', 'mime' => $mime === 'video/webm' ? 'audio/webm' : ($mime === 'video/mp4' ? 'audio/mp4' : $mime), 'duration' => null];
        }
        try {
            $probe = new Process([(string) config('messaging.ffprobe'), '-v', 'error', '-show_entries', 'format=duration:stream=codec_type,duration:packet=pts_time,duration_time', '-of', 'json', $file->getPathname()]);
            $probe->setTimeout(5)->mustRun();
            $data = json_decode($probe->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            $streams = collect($data['streams'] ?? []);
            $duration = (float) ($data['format']['duration'] ?? $streams->max('duration') ?? 0);
            if ($duration <= 0) {
                $duration = (float) collect($data['packets'] ?? [])->max(fn (array $packet): float => (float) ($packet['pts_time'] ?? 0) + (float) ($packet['duration_time'] ?? 0));
            }
            if ($streams->contains('codec_type', 'video') || ! $streams->contains('codec_type', 'audio') || $duration <= 0 || $duration > (float) config('messaging.audio_max_seconds')) {
                throw new \RuntimeException('Invalid voice media.');
            }

            return ['kind' => 'voice', 'mime' => $mime === 'video/webm' ? 'audio/webm' : ($mime === 'video/mp4' ? 'audio/mp4' : $mime), 'duration' => $duration];
        } catch (Throwable $exception) {
            throw ValidationException::withMessages(['attachment' => 'Voice must be valid audio of at most 120 seconds. Server media validation must be available.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function send(User $actor, CourseConversation $conversation, array $data, ?UploadedFile $file): CourseMessage
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $conversation, $data, $file, &$path): CourseMessage {
                $course = Course::query()->lockForUpdate()->findOrFail($conversation->course_id);
                $locked = CourseConversation::query()->lockForUpdate()->findOrFail($conversation->id);
                $locked->setRelation('course', $course);
                abort_unless($this->policy->send($actor, $locked), 404);
                $existing = $locked->messages()->where('user_id', $actor->id)->where('client_id', $data['client_id'])->first();
                if ($existing) {
                    return $existing;
                }
                $replyId = $data['reply_to_id'] ?? null;
                if ($replyId && ! $locked->messages()->whereKey($replyId)->exists()) {
                    throw ValidationException::withMessages(['reply_to_id' => 'Reply must reference a message in this conversation.']);
                }
                $body = trim($data['body'] ?? '');
                if ($body === '' && ! $file) {
                    throw ValidationException::withMessages(['body' => 'Enter a message or attach a file.']);
                }
                $media = $file ? $this->media($file) : null;
                $message = $locked->messages()->create(['user_id' => $actor->id, 'client_id' => $data['client_id'], 'body' => $body ?: null, 'reply_to_id' => $replyId]);
                if ($file) {
                    $path = $this->files->store($file, 'conversations/'.$locked->id, 'course_messaging', 'attachment');
                    $message->attachments()->create(['storage_path' => $path, 'original_filename' => Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 200, ''), 'mime_type' => $media['mime'], 'kind' => $media['kind'], 'file_size' => $file->getSize(), 'duration_seconds' => $media['duration']]);
                }
                $this->event($locked, 'message', $message->id);

                return $message;
            }, 3);
        } catch (Throwable $exception) {
            if ($path !== null && ! CourseMessageAttachment::query()->where('storage_path', $path)->exists()) {
                Storage::disk('course_messaging')->delete($path);
            }
            throw $exception;
        }
    }

    public function read(User $actor, CourseConversation $conversation, int $messageId): void
    {
        DB::transaction(function () use ($actor, $conversation, $messageId): void {
            $course = Course::query()->lockForUpdate()->findOrFail($conversation->course_id);
            $locked = CourseConversation::query()->lockForUpdate()->findOrFail($conversation->id);
            $locked->setRelation('course', $course);
            abort_unless($this->policy->view($actor, $locked), 404);
            if (! $locked->messages()->whereKey($messageId)->exists()) {
                throw ValidationException::withMessages(['message_id' => 'Read cursor must belong to this conversation.']);
            }
            $cursor = $locked->cursors()->firstOrCreate(['user_id' => $actor->id]);
            if ($messageId > $cursor->last_read_message_id) {
                $cursor->update(['last_read_message_id' => $messageId]);
                $this->event($locked, 'read', $actor->id);
            }
        });
    }

    public function delete(User $actor, CourseMessage $message): void
    {
        DB::transaction(function () use ($actor, $message): void {
            $courseId = CourseConversation::query()->whereKey($message->course_conversation_id)->value('course_id');
            $course = Course::query()->lockForUpdate()->findOrFail($courseId);
            $conversation = CourseConversation::query()->lockForUpdate()->findOrFail($message->course_conversation_id);
            $conversation->setRelation('course', $course);
            $message = $conversation->messages()->lockForUpdate()->findOrFail($message->id);
            abort_unless($message->user_id === $actor->id && $this->policy->send($actor, $conversation), 403);
            if ($message->deleted_at === null) {
                $message->update(['body' => null, 'deleted_at' => now()]);
                $message->reactions()->delete();
                $this->event($conversation, 'deleted', $message->id);
            }
        });
    }

    public function react(User $actor, CourseMessage $message, string $emoji): void
    {
        DB::transaction(function () use ($actor, $message, $emoji): void {
            $courseId = CourseConversation::query()->whereKey($message->course_conversation_id)->value('course_id');
            $course = Course::query()->lockForUpdate()->findOrFail($courseId);
            $conversation = CourseConversation::query()->lockForUpdate()->findOrFail($message->course_conversation_id);
            $conversation->setRelation('course', $course);
            abort_unless($this->policy->send($actor, $conversation), 404);
            $message = $conversation->messages()->lockForUpdate()->findOrFail($message->id);
            abort_if($message->deleted_at !== null, 422);
            $existing = $message->reactions()->where('user_id', $actor->id)->where('emoji', $emoji)->first();
            if ($existing) {
                $existing->delete();
            } else {
                $message->reactions()->create(['user_id' => $actor->id, 'emoji' => $emoji]);
            }
            $this->event($conversation, 'reaction', $message->id);
        });
    }

    public function event(CourseConversation $conversation, string $kind, ?int $subjectId = null): void
    {
        $conversation->increment('version');
        $conversation->events()->create(['version' => $conversation->version, 'kind' => $kind, 'subject_id' => $subjectId, 'created_at' => now()]);
        if (config('messaging.broadcast')) {
            CourseConversationChanged::dispatch($conversation->id, $conversation->version, $kind);
        }
    }

    public function unreadQuery(User $actor, CourseConversation $conversation): Builder
    {
        $cursor = $conversation->cursors()->where('user_id', $actor->id)->value('last_read_message_id') ?? 0;

        return $conversation->messages()->where('id', '>', $cursor)->where('user_id', '!=', $actor->id)->whereNull('deleted_at')->getQuery();
    }

    /** @return array<string, mixed> */
    public function describe(User $actor, CourseConversation $conversation): array
    {
        $peerId = $actor->id === $conversation->instructor_id ? $conversation->student_id : $conversation->instructor_id;

        return [
            'id' => $conversation->id, 'course' => ['id' => $conversation->course_id, 'title' => $conversation->course->title],
            'kind' => $conversation->kind, 'title' => $conversation->title ?? User::query()->whereKey($peerId)->value('name') ?? 'Private conversation',
            'instructor_id' => $conversation->instructor_id, 'version' => $conversation->version,
            'unread_count' => $this->unreadQuery($actor, $conversation)->count(),
            'can_send' => $this->policy->send($actor, $conversation), 'can_manage' => $this->policy->manage($actor, $conversation),
            'student_ids' => $this->policy->manage($actor, $conversation) && $conversation->kind === 'selected' ? $conversation->members()->whereNull('removed_at')->pluck('user_id') : [],
        ];
    }

    /** @return array<string, mixed> */
    public function describeMessage(User $actor, CourseMessage $message): array
    {
        $message->loadMissing(['user:id,name', 'reply.attachments', 'attachments', 'reactions', 'conversation.course']);
        $readers = User::query()->where('status', UserStatus::Active)->whereIn('id', CourseReadCursor::query()->where('course_conversation_id', $message->course_conversation_id)->where('last_read_message_id', '>=', $message->id)->where('user_id', '!=', $message->user_id)->select('user_id'))->get();
        $readerCount = $readers->filter(fn (User $user): bool => $this->policy->view($user, $message->conversation))->count();

        return [
            'id' => $message->id, 'client_id' => $message->client_id, 'user' => ['id' => $message->user_id, 'name' => $message->user?->name ?? ''],
            'body' => $message->deleted_at ? null : $message->body, 'deleted' => $message->deleted_at !== null,
            'created_at' => $message->created_at->toIso8601String(),
            'reply' => $message->reply ? [
                'id' => $message->reply->id,
                'body' => $message->reply->deleted_at ? null : $message->reply->body,
                'deleted' => $message->reply->deleted_at !== null,
                'attachment' => $message->reply->deleted_at ? null : $message->reply->attachments->take(1)->map(fn (CourseMessageAttachment $file): array => [
                    'id' => $file->id, 'name' => $file->original_filename, 'kind' => $file->kind, 'url' => route('messaging.attachments.show', $file->id),
                ])->first(),
            ] : null,
            'attachments' => $message->deleted_at ? [] : $message->attachments->map(fn (CourseMessageAttachment $file): array => ['id' => $file->id, 'name' => $file->original_filename, 'kind' => $file->kind, 'mime' => $file->mime_type, 'size' => $file->file_size, 'duration' => $file->duration_seconds, 'url' => route('messaging.attachments.show', $file->id)]),
            'reactions' => $message->deleted_at ? [] : $message->reactions->groupBy('emoji')->map(fn ($reactions, string $emoji): array => ['emoji' => $emoji, 'count' => $reactions->count(), 'mine' => $reactions->contains('user_id', $actor->id)])->values(),
            'reader_count' => $readerCount,
            'can_delete' => $message->user_id === $actor->id && ! $message->deleted_at && $this->policy->send($actor, $message->conversation),
        ];
    }
}
