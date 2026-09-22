<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
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
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'promo_video_url' => $this->promo_video_url,
            'level' => $this->level->value,
            'language' => $this->language,
            'duration_minutes' => $this->duration_minutes,
            'access_duration_days' => $this->access_duration_days,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'discount_starts_at' => $this->discount_starts_at,
            'discount_ends_at' => $this->discount_ends_at,
            'certificate_enabled' => $this->certificate_enabled,
            'discussion_enabled' => $this->discussion_enabled,
            'status' => $this->status->value,
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'instructor' => new UserSummaryResource($this->whenLoaded('instructor')),
            'learning_outcomes' => $this->whenLoaded('learningOutcomes', fn () => $this->learningOutcomes->map->only(['id', 'outcome', 'sort_order'])),
            'requirements' => $this->whenLoaded('requirements', fn () => $this->requirements->map->only(['id', 'requirement', 'sort_order'])),
            'target_audiences' => $this->whenLoaded('targetAudiences', fn () => $this->targetAudiences->map->only(['id', 'audience', 'sort_order'])),
            'required_tools' => $this->whenLoaded('requiredTools', fn () => $this->requiredTools->map->only(['id', 'tool', 'sort_order'])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
