<?php

namespace App\Models;

use App\SupportTicketCategory;
use App\SupportTicketPriority;
use App\SupportTicketStatus;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['ticket_number', 'user_id', 'assigned_to', 'subject', 'category', 'priority', 'status', 'related_order_id', 'related_course_id', 'last_reply_at', 'resolved_at', 'closed_at'])]
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function relatedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'related_order_id');
    }

    public function relatedCourse(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'related_course_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportTicketMessage::class)->oldest()->orderBy('id');
    }

    public function visibleMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class)->oldest()->orderBy('id');
    }

    protected function casts(): array
    {
        return ['category' => SupportTicketCategory::class, 'priority' => SupportTicketPriority::class, 'status' => SupportTicketStatus::class, 'last_reply_at' => 'datetime', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }
}
