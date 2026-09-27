<?php

namespace App\Models;

use App\CourseReviewStatus;
use Database\Factories\CourseReviewHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['course_review_id', 'actor_id', 'actor_name_snapshot', 'action', 'from_status', 'to_status', 'old_rating', 'new_rating', 'old_title', 'new_title', 'old_body', 'new_body', 'reason', 'created_at'])]
class CourseReviewHistory extends Model
{
    public const UPDATED_AT = null;

    /** @use HasFactory<CourseReviewHistoryFactory> */
    use HasFactory;

    public function review(): BelongsTo
    {
        return $this->belongsTo(CourseReview::class, 'course_review_id')->withTrashed();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected function casts(): array
    {
        return [
            'from_status' => CourseReviewStatus::class,
            'to_status' => CourseReviewStatus::class,
            'old_rating' => 'integer',
            'new_rating' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
