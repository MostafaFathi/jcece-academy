<?php

namespace App\Models;

use Database\Factories\CourseFaqFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'question_ar', 'answer_ar', 'question_en', 'answer_en', 'sort_order', 'is_active'])]
class CourseFaq extends Model
{
    /** @use HasFactory<CourseFaqFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
