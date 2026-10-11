<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnouncementRecipient extends Model
{
    protected $fillable = ['announcement_id', 'user_id', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
