<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CourseConversation;
use App\Models\NotificationPreference;
use App\Models\PlatformNotification;
use App\Services\NotificationTargetService;
use App\Services\NotificationTypeRegistry;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(private NotificationTypeRegistry $registry, private NotificationTargetService $targets) {}

    private function inbox(Request $request): Builder
    {
        abort_unless($request->user()->status === UserStatus::Active, 403);

        return PlatformNotification::query()->where('notifiable_type', $request->user()->getMorphClass())->where('notifiable_id', $request->user()->id);
    }

    public function index(Request $request): JsonResponse
    {
        $valid = $request->validate(['unread' => ['sometimes', 'boolean'], 'category' => ['nullable', Rule::in(array_unique(array_column($this->registry->all(), 'category')))], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'], 'page' => ['sometimes', 'integer', 'min:1']]);
        $query = $this->inbox($request);
        $unread = (clone $query)->whereNull('read_at')->count();
        if ($request->boolean('unread')) {
            $query->whereNull('read_at');
        }if ($request->filled('category')) {
            $query->where('category', $valid['category']);
        }
        $page = $query->orderByDesc('updated_at')->orderByDesc('id')->paginate($valid['per_page'] ?? 20);
        $locale = in_array($request->user()->preferred_locale, ['ar', 'en'], true) ? $request->user()->preferred_locale : app()->getLocale();
        $page->through(fn ($n) => ['id' => $n->id, 'type' => $n->type, 'category' => $n->category, 'priority' => $n->priority, 'title' => $this->registry->get($n->type)[$locale === 'en' ? 'en' : 'ar'], 'titles' => array_intersect_key($this->registry->get($n->type), array_flip(['ar', 'en'])), 'read_at' => $n->read_at, 'created_at' => $n->created_at, 'count' => $n->aggregate_count]);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'unread_count' => $unread]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $this->inbox($request)->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->json(['status' => 'read']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $this->inbox($request)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['status' => 'read']);
    }

    public function open(Request $request, string $notification): JsonResponse
    {
        $n = $this->inbox($request)->whereKey($notification)->firstOrFail();
        $path = $this->targets->path($request->user(), $n->target_type, $n->target_id);
        abort_unless($path, 404);
        $n->markAsRead();

        return response()->json(['path' => $path]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $this->inbox($request);
        $rows = [];
        foreach ($this->registry->all() as $type => $definition) {
            $rows[] = ['type' => $type, 'mandatory_email' => $definition['mandatory'] && $definition['email'], ...$definition, ...$this->registry->channels($request->user(), $type)];
        }

        return response()->json(['data' => $rows]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $this->inbox($request);
        $attributes = $request->validate(['preferences' => ['required', 'array', 'max:100'], 'preferences.*.type' => ['required', Rule::in(array_keys($this->registry->all()))], 'preferences.*.in_app' => ['required', 'boolean'], 'preferences.*.email' => ['required', 'boolean'], 'preferences.*.digest' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $attributes): void {
            foreach ($attributes['preferences'] as $row) {
                $definition = $this->registry->get($row['type']);
                if ($definition['mandatory']) {
                    $row['in_app'] = true;
                    if ($definition['email']) {
                        $row['email'] = true;
                    }$row['digest'] = false;
                }NotificationPreference::query()->updateOrCreate(['user_id' => $request->user()->id, 'type' => $row['type']], $row);
            }
        });

        return $this->preferences($request);
    }

    public function presence(Request $request): JsonResponse
    {
        $this->inbox($request);
        $data = $request->validate(['conversation_id' => ['required', 'integer']]);
        $conversation = CourseConversation::query()->with('course')->findOrFail($data['conversation_id']);
        abort_unless(Gate::forUser($request->user())->allows('view', $conversation), 404);
        $key = "notification-presence:{$request->user()->id}:{$conversation->id}";
        if ($request->isMethod('delete')) {
            Cache::forget($key);
        } else {
            Cache::put($key, true, 25);
        }

        return response()->json(['status' => 'ok']);
    }
}
