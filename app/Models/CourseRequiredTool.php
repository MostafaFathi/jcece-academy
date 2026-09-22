<?php

namespace App\Models;

use Database\Factories\CourseRequiredToolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'tool', 'sort_order'])]
class CourseRequiredTool extends Model
{
    /** @use HasFactory<CourseRequiredToolFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
