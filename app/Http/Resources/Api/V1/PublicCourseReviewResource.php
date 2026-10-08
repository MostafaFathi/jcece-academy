<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCourseReviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'reviewer_name' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'course' => $this->whenLoaded('course', fn (): array => ['title' => $this->course->title, 'slug' => $this->course->slug]),
            'published_at' => $this->published_at,
        ];
    }
}
