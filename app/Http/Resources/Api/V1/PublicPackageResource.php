<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPackageResource extends JsonResource
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
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'type' => $this->type->value,
            'price' => $this->price,
            'compare_price' => $this->compare_price,
            'access_duration_days' => $this->access_duration_days,
            'is_lifetime' => $this->access_duration_days === null,
            'is_sequential' => $this->is_sequential,
            'course_count' => $this->whenCounted('courseMemberships'),
            'courses' => PublicPackageCourseResource::collection($this->whenLoaded('courseMemberships')),
        ];
    }
}
