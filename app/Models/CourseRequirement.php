<?php

namespace App\Models;

use Database\Factories\CourseRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'requirement', 'sort_order'])]
class CourseRequirement extends Model
{
    /** @use HasFactory<CourseRequirementFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
