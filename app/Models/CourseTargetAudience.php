<?php

namespace App\Models;

use Database\Factories\CourseTargetAudienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_id', 'audience', 'sort_order'])]
class CourseTargetAudience extends Model
{
    /** @use HasFactory<CourseTargetAudienceFactory> */
    use HasFactory;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
