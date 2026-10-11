<?php

namespace App\Models;

use Illuminate\Notifications\DatabaseNotification;

class PlatformNotification extends DatabaseNotification
{
    protected $table = 'notifications';

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }
}
