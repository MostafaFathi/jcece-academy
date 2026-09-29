<?php

namespace App\Models;

use Database\Factories\LessonResourceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lesson_id', 'title', 'type', 'file_path', 'external_url', 'is_downloadable', 'sort_order'])]
#[Hidden(['file_path', 'external_url', 'storage_path', 'storage_disk'])]
class LessonResource extends Model
{
    /** @use HasFactory<LessonResourceFactory> */
    use HasFactory;

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_downloadable' => 'boolean'];
    }
}
