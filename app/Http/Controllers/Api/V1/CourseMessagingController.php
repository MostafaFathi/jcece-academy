<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CourseGroupRequest;
use App\Http\Requests\Api\V1\StoreCourseMessageRequest;
use App\Http\Resources\Api\V1\CourseConversationResource;
use App\Http\Resources\Api\V1\CourseMessageResource;
use App\Models\Course;
use App\Models\CourseMessage;
use App\Models\CourseMessageAttachment;
use App\Models\User;
use App\Policies\CourseConversationPolicy;
use App\Services\CourseMessagingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseMessagingController extends Controller
{
    public function __construct(private CourseMessagingService $service, private CourseConversationPolicy $policy) {}

    public function courses(Request $request): JsonResponse
    {
        $courses = Course::query()->where(fn (Builder $query) => $query->where('instructor_id', $request->user()->id)->orWhereHas('enrollments', fn (Builder $enrollments) => $enrollments->where('user_id', $request->user()->id)))->orderBy('title')->get();

        return response()->json(['data' => $courses->filter(fn (Course $course): bool => $this->policy->eligible($request->user(), $course))->map(fn (Course $course): array => ['id' => $course->id, 'title' => $course->title, 'is_instructor' => $course->instructor_id === $request->user()->id, 'can_manage' => $course->instructor_id === $request->user()->id && $request->user()->can('messaging.groups.manage')])->values()]);
    }

    public function students(Request $request, Course $course): JsonResponse
    {
        abort_unless($course->instructor_id === $request->user()->id && $this->policy->eligible($request->user(), $course), 404);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);
        $users = User::query()->where('id', '!=', $course->instructor_id)->whereHas('enrollments', fn (Builder $query) => $query->where('course_id', $course->id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where('name', 'like', '%'.$search.'%'))->orderBy('name')->get()
            ->filter(fn (User $user): bool => $this->policy->eligible($user, $course))->values();
        $page = (int) ($filters['page'] ?? 1);

        return response()->json(['data' => $users->slice(($page - 1) * 50, 50)->map(fn (User $user): array => ['id' => $user->id, 'name' => $user->name])->values(), 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($users->count() / 50))]]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate(['course_id' => ['nullable', 'integer'], 'search' => ['nullable', 'string', 'max:120'], 'unread' => ['nullable', 'boolean']]);
        $query = $this->service->visibleQuery($request->user())
            ->when($filters['course_id'] ?? null, fn (Builder $query, int $id) => $query->where('course_id', $id))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $names) => $names->where('title', 'like', '%'.$search.'%')->orWhereHas('course', fn (Builder $courses) => $courses->where('title', 'like', '%'.$search.'%'))->orWhereHas('student', fn (Builder $users) => $users->where('name', 'like', '%'.$search.'%'))->orWhereHas('instructor', fn (Builder $users) => $users->where('name', 'like', '%'.$search.'%'))));
        if ($request->boolean('unread')) {
            $query->whereHas('messages', function (Builder $messages) use ($request): void {
                $messages->where('user_id', '!=', $request->user()->id)->whereNull('deleted_at')
                    ->whereRaw('course_messages.id > COALESCE((SELECT last_read_message_id FROM course_read_cursors WHERE course_read_cursors.course_conversation_id = course_messages.course_conversation_id AND course_read_cursors.user_id = ?), 0)', [$request->user()->id]);
            });
        }

        return CourseConversationResource::collection($query->orderByDesc('updated_at')->orderByDesc('id')->paginate(25));
    }

    public function show(Request $request, int $conversation): CourseConversationResource
    {
        return new CourseConversationResource($this->service->conversation($request->user(), $conversation));
    }

    public function privateChat(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate(['student_id' => ['nullable', 'integer']]);

        return (new CourseConversationResource($this->service->privateConversation($request->user(), $course, $data['student_id'] ?? null)))->response()->setStatusCode(200);
    }

    public function group(CourseGroupRequest $request, Course $course): JsonResponse
    {
        $data = $request->validated();

        return (new CourseConversationResource($this->service->group($request->user(), $course, $data['title'], $data['kind'], $data['student_ids'] ?? [])))->response()->setStatusCode(201);
    }

    public function members(CourseGroupRequest $request, int $conversation): CourseConversationResource
    {
        $data = $request->validated();

        return new CourseConversationResource($this->service->members($request->user(), $this->service->conversation($request->user(), $conversation), $data['student_ids'] ?? []));
    }

    public function messages(Request $request, int $conversation): AnonymousResourceCollection
    {
        $owned = $this->service->conversation($request->user(), $conversation);
        $filters = $request->validate(['before' => ['nullable', 'integer', 'min:1'], 'search' => ['nullable', 'string', 'max:120']]);

        return CourseMessageResource::collection($owned->messages()->with(['user:id,name', 'attachments', 'reply', 'reactions', 'conversation.course'])
            ->when($filters['before'] ?? null, fn (Builder $query, int $before) => $query->where('id', '<', $before))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereNull('deleted_at')->where('body', 'like', '%'.$search.'%'))
            ->orderByDesc('id')->paginate(50));
    }

    public function send(StoreCourseMessageRequest $request, int $conversation): JsonResponse
    {
        $owned = $this->service->conversation($request->user(), $conversation);

        return (new CourseMessageResource($this->service->send($request->user(), $owned, $request->safe()->except('attachment'), $request->file('attachment'))))->response()->setStatusCode(201);
    }

    public function read(Request $request, int $conversation): JsonResponse
    {
        $data = $request->validate(['message_id' => ['required', 'integer', 'min:1']]);
        $this->service->read($request->user(), $this->service->conversation($request->user(), $conversation), $data['message_id']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function events(Request $request, int $conversation): JsonResponse
    {
        $owned = $this->service->conversation($request->user(), $conversation);
        $data = $request->validate(['after' => ['nullable', 'integer', 'min:0']]);
        $events = $owned->events()->where('version', '>', $data['after'] ?? 0)->orderBy('version')->limit(100)->get(['version', 'kind', 'subject_id']);

        return response()->json(['data' => $events, 'version' => $owned->version]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = $this->service->visibleQuery($request->user())->orderByDesc('updated_at')->get()->map(function ($conversation) use ($request): array {
            return ['conversation_id' => $conversation->id, 'course_id' => $conversation->course_id, 'course_title' => $conversation->course->title, 'count' => $this->service->unreadQuery($request->user(), $conversation)->count()];
        })->filter(fn (array $item): bool => $item['count'] > 0)->values();

        return response()->json(['data' => $notifications, 'unread_count' => $notifications->sum('count')]);
    }

    private function message(Request $request, int $id): CourseMessage
    {
        $message = CourseMessage::query()->findOrFail($id);
        $this->service->conversation($request->user(), $message->course_conversation_id);

        return $message;
    }

    public function messageDetail(Request $request, int $message): CourseMessageResource
    {
        return new CourseMessageResource($this->message($request, $message));
    }

    public function delete(Request $request, int $message): JsonResponse
    {
        $this->service->delete($request->user(), $this->message($request, $message));

        return response()->json(['data' => ['ok' => true]]);
    }

    public function react(Request $request, int $message): JsonResponse
    {
        $data = $request->validate(['emoji' => ['required', Rule::in(['👍', '❤️', '😂', '🎉', '😮', '🙏'])]]);
        $this->service->react($request->user(), $this->message($request, $message), $data['emoji']);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file = CourseMessageAttachment::query()->with('message')->findOrFail($attachment);
        $this->service->conversation($request->user(), $file->message->course_conversation_id);
        abort_if($file->message->deleted_at !== null, 404);

        return Storage::disk('course_messaging')->response($file->storage_path, $file->original_filename, ['Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store'], 'inline');
    }
}
