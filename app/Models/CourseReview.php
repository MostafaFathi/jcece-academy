<?php

namespace App\Models;

use App\CourseReviewStatus;
use Database\Factories\CourseReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['course_id', 'user_id', 'rating', 'title', 'body', 'status', 'submitted_at', 'published_at', 'moderated_by', 'moderated_at', 'moderation_reason'])]
class CourseReview extends Model
{
    /** @use HasFactory<CourseReviewFactory> */
    use HasFactory, SoftDeletes;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by')->withTrashed();
    }

    public function histories(): HasMany
    {
        return $this->hasMany(CourseReviewHistory::class)->orderBy('created_at')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => CourseReviewStatus::class,
            'submitted_at' => 'datetime',
            'published_at' => 'datetime',
            'moderated_at' => 'datetime',
        ];
    }
}
