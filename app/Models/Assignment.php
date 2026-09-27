<?php

namespace App\Models;

use App\AssignmentStatus;
use App\AssignmentSubmissionType;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['course_id', 'lesson_id', 'title', 'description', 'instructions', 'status', 'submission_type', 'maximum_score', 'passing_score', 'max_attempts', 'available_from', 'due_at', 'allow_late_submissions'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, SoftDeletes;

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AssignmentAttachment::class)->oldest()->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class)->latest()->orderByDesc('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AssignmentStatus::class,
            'submission_type' => AssignmentSubmissionType::class,
            'maximum_score' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'available_from' => 'datetime',
            'due_at' => 'datetime',
            'allow_late_submissions' => 'boolean',
        ];
    }
}
