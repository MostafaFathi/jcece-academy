<?php

namespace App\Models;

use App\AssignmentSubmissionStatus;
use App\AssignmentSubmissionType;
use Database\Factories\AssignmentSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['assignment_id', 'enrollment_id', 'user_id', 'attempt_number', 'status', 'active_key', 'text_answer', 'submitted_at', 'is_late', 'assignment_title', 'assignment_instructions', 'maximum_score', 'passing_score', 'submission_type', 'due_at', 'allow_late_submissions', 'score', 'passed', 'feedback', 'graded_by', 'graded_at'])]
class AssignmentSubmission extends Model
{
    /** @use HasFactory<AssignmentSubmissionFactory> */
    use HasFactory;

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(AssignmentSubmissionFile::class)->oldest()->orderBy('id');
    }

    public function gradingEvents(): HasMany
    {
        return $this->hasMany(AssignmentGradingEvent::class)->oldest()->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AssignmentSubmissionStatus::class,
            'submitted_at' => 'datetime',
            'is_late' => 'boolean',
            'maximum_score' => 'decimal:2',
            'passing_score' => 'decimal:2',
            'submission_type' => AssignmentSubmissionType::class,
            'due_at' => 'datetime',
            'allow_late_submissions' => 'boolean',
            'score' => 'decimal:2',
            'passed' => 'boolean',
            'graded_at' => 'datetime',
        ];
    }
}
