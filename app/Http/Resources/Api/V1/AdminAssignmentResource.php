<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class AdminAssignmentResource extends JsonResource
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
            'submission_type' => $this->submission_type->value,
            'maximum_score' => $this->maximum_score,
            'passing_score' => $this->passing_score,
            'max_attempts' => $this->max_attempts,
            'available_from' => $this->available_from,
            'due_at' => $this->due_at,
            'allow_late_submissions' => $this->allow_late_submissions,
            'attachments' => AssignmentAttachmentResource::collection($this->whenLoaded('attachments')),
            'capabilities' => $this->when($request->routeIs('api.v1.instructor.*', 'api.v1.admin.*'), fn (): array => [
                'can_update' => Gate::forUser($request->user())->allows('update', $this->resource),
                'can_delete' => Gate::forUser($request->user())->allows('delete', $this->resource),
                'can_publish' => Gate::forUser($request->user())->allows('publish', $this->resource),
                'can_review_submissions' => Gate::forUser($request->user())->allows('reviewSubmissions', $this->resource),
            ]),
        ];
    }
}
