<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminQuizAttemptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'enrollment_id' => $this->enrollment_id,
            'user_id' => $this->user_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'passing_score' => $this->passing_score,
            'started_at' => $this->started_at,
            'expires_at' => $this->expires_at,
            'submitted_at' => $this->submitted_at,
            'score' => $this->score,
            'maximum_score' => $this->maximum_score,
            'percentage' => $this->percentage,
            'passed' => $this->passed,
            'questions' => $this->whenLoaded('questions', fn () => $this->questions->map(fn ($question): array => [
                'id' => $question->id,
                'source_question_id' => $question->source_question_id,
                'type' => $question->type->value,
                'question_text' => $question->question_text,
                'explanation' => $question->explanation,
                'points' => $question->points,
                'sort_order' => $question->sort_order,
                'selected_option_ids' => $question->answer?->selected_option_ids ?? [],
                'earned_points' => $question->answer?->earned_points,
                'options' => $question->options->map(fn ($option): array => [
                    'id' => $option->id,
                    'source_option_id' => $option->source_option_id,
                    'answer_text' => $option->answer_text,
                    'is_correct' => $option->is_correct,
                    'sort_order' => $option->sort_order,
                ])->all(),
            ])->all()),
        ];
    }
}
