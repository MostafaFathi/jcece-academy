<?php

namespace App\Models;

use App\QuizQuestionType;
use Database\Factories\QuizAttemptQuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['quiz_attempt_id', 'source_question_id', 'type', 'question_text', 'explanation', 'points', 'sort_order'])]
class QuizAttemptQuestion extends Model
{
    /** @use HasFactory<QuizAttemptQuestionFactory> */
    use HasFactory;

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'quiz_attempt_id');
    }

    public function sourceQuestion(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'source_question_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(QuizAttemptOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function answer(): HasOne
    {
        return $this->hasOne(QuizAttemptAnswer::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => QuizQuestionType::class,
            'points' => 'decimal:2',
        ];
    }
}
