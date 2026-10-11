<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationDelivery extends Model
{
    protected $fillable = ['user_id', 'event_key', 'type', 'channel', 'payload', 'status', 'attempts', 'available_at', 'last_attempt_at', 'sent_at', 'error_code'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'last_attempt_at' => 'datetime', 'sent_at' => 'datetime'];
    }
}
