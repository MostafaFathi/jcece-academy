<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable(['lesson_id', 'uploaded_by', 'request_id', 'video_guid', 'status', 'filename', 'mime_type', 'size_bytes', 'encode_progress', 'is_current', 'uploaded_at', 'ready_at', 'deleted_at'])]
class LessonVideoUpload extends Model
{
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return [
            'is_current' => 'boolean',
            'uploaded_at' => 'datetime',
            'ready_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
