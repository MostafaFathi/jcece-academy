<?php

namespace App\Services;

use App\Events\SupportTicketCreated;
use App\Events\SupportTicketStatusChanged;
use App\Models\Course;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\User;
use App\PermissionName;
use App\SupportTicketActivityType;
use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupportTicketService
{
    public function __construct(private CourseAccessService $courseAccess, private SupportTicketMessageService $messages) {}

    /** @param array<string, mixed> $attributes @param list<\Illuminate\Http\UploadedFile> $attachments */
    public function create(User $user, array $attributes, array $attachments = []): SupportTicket
    {
        $orderId = $attributes['related_order_id'] ?? null;
        $courseId = $attributes['related_course_id'] ?? null;
        if ($orderId !== null && ! Order::query()->whereKey($orderId)->whereBelongsTo($user)->exists()) {
            throw ValidationException::withMessages(['related_order_id' => 'The selected related order is invalid.']);
        }
        if ($courseId !== null) {
            $course = Course::query()->find($courseId);
            if ($course === null || ! $this->courseAccess->hasAccess($user, $course)) {
                throw ValidationException::withMessages(['related_course_id' => 'The selected related course is invalid.']);
            }
        }
        $ticket = DB::transaction(function () use ($user, $attributes, $attachments): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'ticket_number' => 'JCEC-'.Str::upper((string) Str::ulid()), 'user_id' => $user->id,
                'subject' => $attributes['subject'], 'category' => $attributes['category'],
                'priority' => SupportTicketPriority::Normal, 'status' => SupportTicketStatus::Open,
                'related_order_id' => $attributes['related_order_id'] ?? null, 'related_course_id' => $attributes['related_course_id'] ?? null,
            ]);
            $this->activity($ticket, $user, SupportTicketActivityType::Created);
            $this->messages->post($user, $ticket, $attributes['body'], false, $attachments, false);

            return $ticket;
        });
        SupportTicketCreated::dispatch($ticket);

        return $this->load($ticket);
    }

    /** @param array<string, mixed> $changes */
    public function update(User $actor, SupportTicket $ticket, array $changes): SupportTicket
    {
        $fromStatus = $ticket->status;
        $updated = DB::transaction(function () use ($actor, $ticket, $changes): SupportTicket {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureFresh($locked, $changes['expected_updated_at'] ?? null);
            foreach (['category', 'priority'] as $field) {
                if (array_key_exists($field, $changes) && $locked->{$field}->value !== $changes[$field]) {
                    $previous = $locked->{$field}->value;
                    $locked->update([$field => $changes[$field]]);
                    $type = $field === 'category' ? SupportTicketActivityType::CategoryChanged : SupportTicketActivityType::PriorityChanged;
                    $this->activity($locked, $actor, $type, $field, $previous, $changes[$field]);
                }
            }
            if (isset($changes['status']) && $locked->status->value !== $changes['status']) {
                $this->transitionLocked($actor, $locked, SupportTicketStatus::from($changes['status']));
            }

            return $locked->refresh();
        });
        if ($updated->status !== $fromStatus) {
            SupportTicketStatusChanged::dispatch($updated, $fromStatus, $updated->status);
        }

        return $this->load($updated, true);
    }

    public function assign(User $actor, SupportTicket $ticket, User $assignee, ?string $expectedUpdatedAt = null): SupportTicket
    {
        if (! $assignee->can(PermissionName::SupportTicketsManage->value)) {
            throw ValidationException::withMessages(['assigned_to' => 'The selected assignee is not eligible to manage support tickets.']);
        }

        return DB::transaction(function () use ($actor, $ticket, $assignee, $expectedUpdatedAt): SupportTicket {
            $locked = SupportTicket::query()->lockForUpdate()->findOrFail($ticket->id);
            $this->ensureFresh($locked, $expectedUpdatedAt);
            $previous = $locked->assigned_to;
            if ($previous !== $assignee->id) {
                $locked->update(['assigned_to' => $assignee->id]);
                $type = $previous === null ? SupportTicketActivityType::Assigned : SupportTicketActivityType::Reassigned;
                $this->activity($locked, $actor, $type, 'assigned_to', $previous === null ? null : (string) $previous, (string) $assignee->id);
            }

            return $this->load($locked->refresh(), true);
        });
    }

    public function reopen(User $actor, SupportTicket $ticket): SupportTicket
    {
        return $this->update($actor, $ticket, ['status' => SupportTicketStatus::Open->value]);
    }

    public function transitionForReply(User $actor, SupportTicket $ticket, bool $staff): SupportTicket
    {
        $target = null;
        if ($staff && in_array($ticket->status, [SupportTicketStatus::Open, SupportTicketStatus::InProgress], true)) {
            $target = SupportTicketStatus::WaitingForStudent;
        }
        if (! $staff && $ticket->status === SupportTicketStatus::WaitingForStudent) {
            $target = SupportTicketStatus::InProgress;
        }
        if (! $staff && $ticket->status === SupportTicketStatus::Resolved) {
            $target = SupportTicketStatus::Open;
        }

        return $target === null ? $ticket : $this->update($actor, $ticket, ['status' => $target->value]);
    }

    public function load(SupportTicket $ticket, bool $admin = false): SupportTicket
    {
        $relations = ['user:id,name', 'assignee:id,name', 'relatedOrder:id,order_number', 'relatedCourse:id,title,slug'];
        if ($admin) {
            $relations[] = 'activities.actor:id,name';
        }

        return $ticket->load($relations);
    }

    private function transitionLocked(User $actor, SupportTicket $ticket, SupportTicketStatus $to): void
    {
        $from = $ticket->status;
        $allowed = [
            SupportTicketStatus::Open->value => [SupportTicketStatus::InProgress, SupportTicketStatus::WaitingForStudent, SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
            SupportTicketStatus::InProgress->value => [SupportTicketStatus::WaitingForStudent, SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
            SupportTicketStatus::WaitingForStudent->value => [SupportTicketStatus::InProgress, SupportTicketStatus::Resolved, SupportTicketStatus::Closed],
            SupportTicketStatus::Resolved->value => [SupportTicketStatus::Open, SupportTicketStatus::Closed],
            SupportTicketStatus::Closed->value => [SupportTicketStatus::Open],
        ];
        if (! in_array($to, $allowed[$from->value], true)) {
            throw ValidationException::withMessages(['status' => "A ticket cannot transition from {$from->value} to {$to->value}."]);
        }
        $ticket->update(['status' => $to, 'resolved_at' => $to === SupportTicketStatus::Resolved ? now() : null, 'closed_at' => $to === SupportTicketStatus::Closed ? now() : null]);
        $type = match ($to) {
            SupportTicketStatus::Resolved => SupportTicketActivityType::Resolved, SupportTicketStatus::Closed => SupportTicketActivityType::Closed, SupportTicketStatus::Open => SupportTicketActivityType::Reopened, default => SupportTicketActivityType::StatusChanged
        };
        $this->activity($ticket, $actor, $type, 'status', $from->value, $to->value);
    }

    private function ensureFresh(SupportTicket $ticket, ?string $expected): void
    {
        if ($expected !== null && $ticket->updated_at?->toJSON() !== Carbon::parse($expected)->toJSON()) {
            throw ValidationException::withMessages(['expected_updated_at' => 'The ticket was changed by another request. Refresh it and try again.']);
        }
    }

    private function activity(SupportTicket $ticket, User $actor, SupportTicketActivityType $type, ?string $field = null, ?string $previous = null, ?string $new = null): void
    {
        $ticket->activities()->create(['actor_id' => $actor->id, 'actor_name_snapshot' => $actor->name, 'event_type' => $type, 'field_name' => $field, 'previous_value' => $previous, 'new_value' => $new]);
    }
}
