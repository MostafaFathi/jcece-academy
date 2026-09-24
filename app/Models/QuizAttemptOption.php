<?php

namespace App\Models;

use Database\Factories\QuizAttemptOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['quiz_attempt_question_id', 'source_option_id', 'answer_text', 'is_correct', 'sort_order'])]
#[Hidden(['is_correct'])]
class QuizAttemptOption extends Model
{
    /** @use HasFactory<QuizAttemptOptionFactory> */
    use HasFactory;

    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizAttemptQuestion::class, 'quiz_attempt_question_id');
    }

    public function sourceOption(): BelongsTo
    {
        return $this->belongsTo(QuizOption::class, 'source_option_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }
}
