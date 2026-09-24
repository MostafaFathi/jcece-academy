<?php

namespace App\Http\Resources\Api\V1;

use App\QuizAttemptStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentQuizAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSubmitted = $this->status === QuizAttemptStatus::Submitted;
        $showResults = $isSubmitted && $this->show_results;
        $showCorrectAnswers = $isSubmitted && $this->show_correct_answers;

        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'started_at' => $this->started_at,
            'expires_at' => $this->expires_at,
            'submitted_at' => $this->submitted_at,
            'score' => $this->when($showResults, $this->score),
            'maximum_score' => $this->when($showResults, $this->maximum_score),
            'percentage' => $this->when($showResults, $this->percentage),
            'passed' => $this->when($showResults, $this->passed),
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($question): array => [
                'id' => $question->id,
                'type' => $question->type->value,
                'question_text' => $question->question_text,
                'points' => $question->points,
                'sort_order' => $question->sort_order,
                'selected_option_ids' => $question->answer?->selected_option_ids ?? [],
                'earned_points' => $this->when($showResults, $question->answer?->earned_points ?? '0.00'),
                'explanation' => $this->when($showCorrectAnswers, $question->explanation),
                'correct_option_ids' => $this->when(
                    $showCorrectAnswers,
                    fn (): array => $question->options->where('is_correct', true)->modelKeys(),
                ),
                'options' => $question->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'answer_text' => $option->answer_text,
                    'sort_order' => $option->sort_order,
                ])->all(),
            ])->all()),
        ];
    }
}
