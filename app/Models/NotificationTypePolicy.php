<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationTypePolicy extends Model
{
    protected $fillable = ['type', 'in_app', 'email'];

    protected function casts(): array
    {
        return ['in_app' => 'boolean', 'email' => 'boolean'];
    }
}
