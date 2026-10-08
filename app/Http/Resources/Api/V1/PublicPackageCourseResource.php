<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CommerceCatalogService;
use App\Services\CommercePricingService;
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
                'price' => app(CommercePricingService::class)->product($this->course)['active_price'],
                'pricing' => app(CommercePricingService::class)->product($this->course),
                'currency' => app(CommerceCatalogService::class)->currency(),
                'instructor' => $this->course->relationLoaded('instructor') ? new UserSummaryResource($this->course->instructor) : null,
                'category' => $this->course->relationLoaded('category') ? new CategoryResource($this->course->category) : null,
            ]),
        ];
    }
}
