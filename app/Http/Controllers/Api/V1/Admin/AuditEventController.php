<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditEventController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('admin'), 403);

        $filters = $request->validate([
            'event_type' => ['nullable', 'string', 'max:100'],
            'actor_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $events = AuditEvent::query()
            ->with('actor:id,name')
            ->when($filters['event_type'] ?? null, fn ($query, $value) => $query->where('event_type', $value))
            ->when($filters['actor_id'] ?? null, fn ($query, $value) => $query->where('actor_id', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->latest('id')
            ->paginate(25);

        return response()->json($events->through(fn (AuditEvent $event): array => [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'subject_type' => $event->subject_type,
            'subject_id' => $event->subject_id,
            'actor' => $event->actor ? ['id' => $event->actor->id, 'name' => $event->actor->name] : null,
            'metadata' => $event->metadata,
            'created_at' => $event->created_at?->toIso8601String(),
        ]));
    }
}
