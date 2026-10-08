<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
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
            'course_section_id' => $this->course_section_id,
            'title' => $this->title,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'description' => $this->description,
            'content' => $this->content,
            'video_provider' => $this->video_provider,
            'video_id' => $this->is_preview ? $this->video_id : null,
            'video_url' => $this->is_preview ? $this->video_url : null,
            'protected_playback_available' => $this->video_provider === 'bunny_stream' && filled($this->protected_video_asset_key),
            'duration_seconds' => $this->duration_seconds,
            'is_preview' => $this->is_preview,
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'resources' => LessonResourceResource::collection($this->whenLoaded('resources')),
        ];
    }
}
