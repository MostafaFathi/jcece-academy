<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLessonProgressResource extends JsonResource
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
            'lesson_id' => $this->lesson_id,
            'status' => $this->status->value,
            'watched_seconds' => $this->watched_seconds,
            'last_position_seconds' => $this->last_position_seconds,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
