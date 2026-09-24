<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\QuizStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizPublicationService
{
    public function __construct(private QuizQuestionService $questions) {}

    public function publish(Quiz $quiz): Quiz
    {
        return DB::transaction(function () use ($quiz): Quiz {
            $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $questions = $lockedQuiz->questions()->with('options')->lockForUpdate()->get();

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages(['quiz' => 'A quiz must contain at least one valid question before publication.']);
            }

            foreach ($questions as $question) {
                /** @var QuizQuestion $question */
                if (BigDecimal::of($question->points)->compareTo(BigDecimal::zero()) <= 0) {
                    throw ValidationException::withMessages(['questions' => 'Every question must have a positive point value.']);
                }

                $this->questions->validateOptions($question->type, $question->options->map(fn ($option): array => [
                    'answer_text' => $option->answer_text,
                    'is_correct' => $option->is_correct,
                ])->all());
            }

            $lockedQuiz->update(['status' => QuizStatus::Published]);

            return $lockedQuiz->refresh()->load('questions.options');
        });
    }

    public function unpublish(Quiz $quiz): Quiz
    {
        return DB::transaction(function () use ($quiz): Quiz {
            $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);

            if ($lockedQuiz->status !== QuizStatus::Archived) {
                $lockedQuiz->update(['status' => QuizStatus::Draft]);
            }

            return $lockedQuiz->refresh()->load('questions.options');
        });
    }
}
