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
            'video_provider' => $this->is_preview ? $this->video_provider : null,
            'video_id' => $this->is_preview ? $this->video_id : null,
            'video_url' => $this->type->value === 'video' && ! $this->is_preview ? null : $this->video_url,
            'protected_playback_available' => $this->type->value === 'video' && ! $this->is_preview && filled($this->protected_video_asset_key),
            'duration_seconds' => $this->duration_seconds,
            'sort_order' => $this->sort_order,
            'resources' => LessonResourceResource::collection($this->whenLoaded('resources')),
            'progress' => new StudentLessonProgressResource($this->whenLoaded('progressRecords', fn () => $this->progressRecords->first())),
        ];
    }
}
