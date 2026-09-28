<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CommerceCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicPackageCourseResource extends JsonResource
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
            'sort_order' => $this->sort_order,
            'is_required' => $this->is_required,
            'course' => $this->whenLoaded('course', fn (): ?array => $this->course === null ? null : [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
                'short_description' => $this->course->short_description,
                'thumbnail' => $this->course->thumbnail,
                'level' => $this->course->level->value,
                'language' => $this->course->language,
                'duration_minutes' => $this->course->duration_minutes,
                'price' => $this->course->price,
                'currency' => app(CommerceCatalogService::class)->currency(),
                'compare_price' => $this->course->compare_price,
            ]),
        ];
    }
}
