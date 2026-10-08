<?php

namespace App\Models;

use Database\Factories\TransactionalDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_key', 'message_type', 'user_id', 'order_id', 'recipient_email', 'locale', 'subject_type', 'subject_id', 'payload', 'status', 'attempts', 'error_code', 'queued_at', 'last_attempt_at', 'sent_at', 'failed_at'])]
class TransactionalDelivery extends Model
{
    /** @use HasFactory<TransactionalDeliveryFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'queued_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }
}
