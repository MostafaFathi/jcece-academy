<?php

namespace App\Http\Resources\Api\V1;

use App\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentEnrollmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Enrollment $enrollment */
        $enrollment = $this->resource['enrollment'];
        /** @var array{has_access: bool, access_state: string, access_expires_at: mixed, is_lifetime: bool} $access */
        $access = $this->resource['access'];
        /** @var array{completed_lessons: int, total_lessons: int, progress_percentage: float, resume: ?array{lesson: Lesson, progress: ?LessonProgress}} $progress */
        $progress = $this->resource['progress'];
        $resume = $progress['resume'];

        return [
            'id' => $enrollment->id,
            'status' => $enrollment->status->value,
            'enrolled_at' => $enrollment->enrolled_at,
            'completed_at' => $enrollment->completed_at,
            'course' => new StudentCourseResource($enrollment->course),
            'has_access' => $access['has_access'],
            'access_state' => $access['access_state'],
            'access_expires_at' => $access['access_expires_at'],
            'is_lifetime' => $access['is_lifetime'],
            'sequential' => $access['sequential'],
            'completed_lessons' => $progress['completed_lessons'],
            'total_lessons' => $progress['total_lessons'],
            'progress_percentage' => $progress['progress_percentage'],
            'resume' => $resume === null ? null : [
                'lesson' => [
                    'id' => $resume['lesson']->id,
                    'title' => $resume['lesson']->title,
                    'slug' => $resume['lesson']->slug,
                    'type' => $resume['lesson']->type->value,
                ],
                'section' => [
                    'id' => $resume['lesson']->section->id,
                    'title' => $resume['lesson']->section->title,
                ],
                'status' => ($resume['progress']?->status ?? LessonProgressStatus::NotStarted)->value,
                'last_position_seconds' => $resume['progress']?->last_position_seconds ?? 0,
            ],
        ];
    }
}
