<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLearningCourseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Course $course */
        $course = $this->resource['course'];
        /** @var Enrollment $enrollment */
        $enrollment = $this->resource['enrollment'];

        return [
            'course' => new StudentCourseResource($course),
            'enrollment_status' => $enrollment->status->value,
            'has_access' => $this->resource['access']['has_access'],
            'access_state' => $this->resource['access']['access_state'],
            'access_expires_at' => $this->resource['access']['access_expires_at'],
            'is_lifetime' => $this->resource['access']['is_lifetime'],
            'sequential' => $this->resource['access']['sequential'],
            'completed_lessons' => $this->resource['progress']['completed_lessons'],
            'total_lessons' => $this->resource['progress']['total_lessons'],
            'progress_percentage' => $this->resource['progress']['progress_percentage'],
            'curriculum' => StudentLearningSectionResource::collection($course->sections),
        ];
    }
}
