<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationReceipt extends Model
{
    protected $fillable = ['user_id', 'event_key', 'type', 'notification_id'];

    protected function casts(): array
    {
        return [];
    }
}
