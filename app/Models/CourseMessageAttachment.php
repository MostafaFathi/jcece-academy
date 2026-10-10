<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_message_id', 'storage_path', 'original_filename', 'mime_type', 'kind', 'file_size', 'duration_seconds'])]
class CourseMessageAttachment extends Model
{
    use HasFactory;

    public function message(): BelongsTo
    {
        return $this->belongsTo(CourseMessage::class, 'course_message_id');
    }

    protected function casts(): array
    {
        return ['duration_seconds' => 'float', 'file_size' => 'integer'];
    }
}
