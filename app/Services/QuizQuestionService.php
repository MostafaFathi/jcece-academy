<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\QuizQuestionType;
use App\QuizStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizQuestionService
{
    /** @param array{type: string, question_text: string, explanation?: ?string, points: numeric-string|int|float, sort_order?: int, options: list<array{answer_text: string, is_correct: bool}>} $attributes */
    public function create(Quiz $quiz, array $attributes): QuizQuestion
    {
        return DB::transaction(function () use ($quiz, $attributes): QuizQuestion {
            $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $type = QuizQuestionType::from($attributes['type']);
            $this->validateOptions($type, $attributes['options']);
            $sortOrder = $attributes['sort_order'] ?? ((int) $lockedQuiz->questions()->max('sort_order') + 1);
            $question = $lockedQuiz->questions()->create([
                'type' => $type,
                'question_text' => $attributes['question_text'],
                'explanation' => $attributes['explanation'] ?? null,
                'points' => $attributes['points'],
                'sort_order' => $sortOrder,
            ]);
            $this->replaceOptions($question, $attributes['options']);

            return $question->load('options');
        });
    }

    /** @param array{type: string, question_text: string, explanation?: ?string, points: numeric-string|int|float, sort_order?: int, options: list<array{answer_text: string, is_correct: bool}>} $attributes */
    public function update(QuizQuestion $question, array $attributes): QuizQuestion
    {
        return DB::transaction(function () use ($question, $attributes): QuizQuestion {
            Quiz::query()->lockForUpdate()->findOrFail($question->quiz_id);
            $lockedQuestion = QuizQuestion::query()->lockForUpdate()->findOrFail($question->id);
            $type = QuizQuestionType::from($attributes['type']);
            $this->validateOptions($type, $attributes['options']);
            $lockedQuestion->update([
                'type' => $type,
                'question_text' => $attributes['question_text'],
                'explanation' => $attributes['explanation'] ?? null,
                'points' => $attributes['points'],
                'sort_order' => $attributes['sort_order'] ?? $lockedQuestion->sort_order,
            ]);
            $lockedQuestion->options()->lockForUpdate()->get()->each->delete();
            $this->replaceOptions($lockedQuestion, $attributes['options']);

            return $lockedQuestion->refresh()->load('options');
        });
    }

    public function delete(QuizQuestion $question): void
    {
        DB::transaction(function () use ($question): void {
            $quiz = Quiz::query()->lockForUpdate()->findOrFail($question->quiz_id);
            $lockedQuestion = QuizQuestion::query()->lockForUpdate()->findOrFail($question->id);
            $lockedQuestion->delete();

            if ($quiz->status === QuizStatus::Published && ! $quiz->questions()->exists()) {
                $quiz->update(['status' => QuizStatus::Draft]);
            }
        });
    }

    /** @param list<array{answer_text: string, is_correct: bool}> $options */
    public function validateOptions(QuizQuestionType $type, array $options): void
    {
        $correctCount = collect($options)->where('is_correct', true)->count();
        $normalizedAnswers = collect($options)->map(fn (array $option): string => mb_strtolower(trim($option['answer_text'])));

        if (count($options) < 2 || $normalizedAnswers->contains('')) {
            throw ValidationException::withMessages(['options' => 'Questions require at least two non-empty answer options.']);
        }

        if ($normalizedAnswers->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['options' => 'Answer options must be distinct.']);
        }

        if ($type === QuizQuestionType::SingleChoice && $correctCount !== 1) {
            throw ValidationException::withMessages(['options' => 'Single-choice questions require exactly one correct option.']);
        }

        if ($type === QuizQuestionType::MultipleChoice && $correctCount < 1) {
            throw ValidationException::withMessages(['options' => 'Multiple-choice questions require at least one correct option.']);
        }

        if ($type === QuizQuestionType::TrueFalse && (count($options) !== 2 || $correctCount !== 1)) {
            throw ValidationException::withMessages(['options' => 'True/false questions require exactly two options and one correct option.']);
        }
    }

    /** @param list<array{answer_text: string, is_correct: bool}> $options */
    private function replaceOptions(QuizQuestion $question, array $options): void
    {
        foreach ($options as $sortOrder => $option) {
            $question->options()->create([
                'answer_text' => $option['answer_text'],
                'is_correct' => $option['is_correct'],
                'sort_order' => $sortOrder,
            ]);
        }
    }
}
