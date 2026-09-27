<?php

namespace App\Models;

use App\SupportTicketActivityType;
use Database\Factories\SupportTicketActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['support_ticket_id', 'actor_id', 'actor_name_snapshot', 'event_type', 'field_name', 'previous_value', 'new_value'])]
class SupportTicketActivity extends Model
{
    /** @use HasFactory<SupportTicketActivityFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected function casts(): array
    {
        return ['event_type' => SupportTicketActivityType::class];
    }
}
