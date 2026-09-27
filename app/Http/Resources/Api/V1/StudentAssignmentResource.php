<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAssignmentResource extends JsonResource
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
            'submission_type' => $this->submission_type->value,
            'maximum_score' => $this->maximum_score,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'available_from' => $this->available_from,
            'due_at' => $this->due_at,
            'allow_late_submissions' => $this->allow_late_submissions,
            'attachments' => AssignmentAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
