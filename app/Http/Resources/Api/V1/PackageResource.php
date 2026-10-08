<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CommerceCatalogService;
use App\Services\CommercePricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageResource extends JsonResource
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
            'price' => app(CommercePricingService::class)->product($this->resource)['active_price'],
            'pricing' => app(CommercePricingService::class)->product($this->resource),
            'currency' => app(CommerceCatalogService::class)->currency(),
            'compare_price' => $this->compare_price,
            'legacy_compare_price' => $this->compare_price,
            'promotional_price' => $this->promotional_price,
            'discount_starts_at' => $this->discount_starts_at,
            'discount_ends_at' => $this->discount_ends_at,
            'access_duration_days' => $this->access_duration_days,
            'is_lifetime' => $this->access_duration_days === null,
            'is_sequential' => $this->is_sequential,
            'sequential_completion_percentage' => $this->is_sequential ? $this->sequential_completion_percentage : null,
            'status' => $this->status->value,
            'published_at' => $this->published_at,
            'course_count' => $this->whenCounted('courseMemberships'),
            'courses' => PackageCourseResource::collection($this->whenLoaded('courseMemberships')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
