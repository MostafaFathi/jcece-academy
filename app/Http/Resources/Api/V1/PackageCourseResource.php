<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageCourseResource extends JsonResource
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
            'package_id' => $this->package_id,
            'course_id' => $this->course_id,
            'sort_order' => $this->sort_order,
            'is_required' => $this->is_required,
            'course' => $this->whenLoaded('course', fn (): ?array => $this->course === null ? null : [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
                'status' => $this->course->status->value,
                'thumbnail' => $this->course->thumbnail,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
