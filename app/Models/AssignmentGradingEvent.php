<?php

namespace App\Models;

use App\AssignmentGradingAction;
use Database\Factories\AssignmentGradingEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assignment_submission_id', 'reviewer_id', 'action', 'previous_score', 'previous_passed', 'previous_feedback', 'score', 'passed', 'feedback', 'reason'])]
class AssignmentGradingEvent extends Model
{
    /** @use HasFactory<AssignmentGradingEventFactory> */
    use HasFactory;

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => AssignmentGradingAction::class,
            'previous_score' => 'decimal:2',
            'previous_passed' => 'boolean',
            'score' => 'decimal:2',
            'passed' => 'boolean',
        ];
    }
}
