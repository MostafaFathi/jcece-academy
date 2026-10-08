<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AdminQuizResource extends JsonResource
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
            'course_id' => $this->course_id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'description' => $this->description,
            'instructions' => $this->instructions,
            'status' => $this->status->value,
            'passing_score' => $this->passing_score,
            'time_limit_minutes' => $this->time_limit_minutes,
            'max_attempts' => $this->max_attempts,
            'shuffle_questions' => $this->shuffle_questions,
            'shuffle_answers' => $this->shuffle_answers,
            'show_results' => $this->show_results,
            'show_correct_answers' => $this->show_correct_answers,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
            'questions' => AdminQuizQuestionResource::collection($this->whenLoaded('questions')),
            'capabilities' => $this->when($request->user() !== null, fn (): array => [
                'can_update' => Gate::forUser($request->user())->allows('update', $this->resource),
                'can_delete' => Gate::forUser($request->user())->allows('delete', $this->resource),
                'can_publish' => Gate::forUser($request->user())->allows('publish', $this->resource),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
