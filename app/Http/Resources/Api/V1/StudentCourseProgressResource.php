<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentCourseProgressResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'enrollment' => new StudentEnrollmentResource([
                'enrollment' => $this->resource['enrollment'],
                'access' => $this->resource['access'],
                'progress' => $this->resource['progress'],
            ]),
            'lesson_progress' => StudentLessonProgressResource::collection($this->resource['lesson_progress']),
        ];
    }
}
