<?php

namespace App\Models;

use Database\Factories\CourseLearningOutcomeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'outcome', 'sort_order'])]
class CourseLearningOutcome extends Model
{
    /** @use HasFactory<CourseLearningOutcomeFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
