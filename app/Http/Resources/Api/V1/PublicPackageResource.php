<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CommerceCatalogService;
use App\Services\CommercePricingService;
use Brick\Math\BigDecimal;
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
        $pricing = app(CommercePricingService::class)->product($this->resource);
        $savings = null;
        if ($this->resource->relationLoaded('courseMemberships')
            && $this->resource->courseMemberships->isNotEmpty()
            && (int) $this->resource->total_memberships_count === $this->resource->courseMemberships->count()) {
            $individualTotal = $this->resource->courseMemberships->reduce(
                fn (BigDecimal $total, $membership): BigDecimal => $total->plus((string) app(CommercePricingService::class)->product($membership->course)['active_price']),
                BigDecimal::zero(),
            );
            $difference = $individualTotal->minus((string) $pricing['active_price']);
            if ($difference->isGreaterThan(0)) {
                $savings = (string) $difference->toScale(2);
            }
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'type' => $this->type->value,
            'price' => $pricing['active_price'],
            'pricing' => $pricing,
            'savings_vs_individual' => $savings,
            'currency' => app(CommerceCatalogService::class)->currency(),
            'compare_price' => app(CommercePricingService::class)->product($this->resource)['promotion_active'] ? $this->price : null,
            'access_duration_days' => $this->access_duration_days,
            'is_lifetime' => $this->access_duration_days === null,
            'is_sequential' => $this->is_sequential,
            'sequential_completion_percentage' => $this->is_sequential ? $this->sequential_completion_percentage : null,
            'course_count' => $this->whenCounted('courseMemberships'),
            'courses' => PublicPackageCourseResource::collection($this->whenLoaded('courseMemberships')),
        ];
    }
}
