<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentLearningLessonResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'description' => $this->description,
            'content' => $this->content,
            'video_provider' => $this->video_provider,
            'video_id' => $this->video_id,
            'video_url' => $this->video_url,
            'duration_seconds' => $this->duration_seconds,
            'sort_order' => $this->sort_order,
            'resources' => LessonResourceResource::collection($this->whenLoaded('resources')),
            'progress' => new StudentLessonProgressResource($this->whenLoaded('progressRecords', fn () => $this->progressRecords->first())),
        ];
    }
}
