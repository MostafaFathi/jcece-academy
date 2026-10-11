<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    protected $fillable = ['created_by', 'published_by', 'title', 'body', 'audience', 'status', 'publish_at', 'published_at', 'expires_at'];

    protected function casts(): array
    {
        return ['title' => 'array', 'body' => 'array', 'audience' => 'array', 'publish_at' => 'datetime', 'published_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
