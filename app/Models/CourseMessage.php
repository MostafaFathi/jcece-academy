<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_conversation_id', 'user_id', 'client_id', 'body', 'reply_to_id', 'deleted_at'])]
class CourseMessage extends Model
{
    use HasFactory;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(CourseConversation::class, 'course_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CourseMessageAttachment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(CourseMessageReaction::class);
    }

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }
}
