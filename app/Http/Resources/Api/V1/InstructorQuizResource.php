<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstructorQuizResource extends JsonResource
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
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'show_results' => $this->show_results,
            'show_correct_answers' => $this->show_correct_answers,
            'available_from' => $this->available_from,
            'available_until' => $this->available_until,
        ];
    }
}
