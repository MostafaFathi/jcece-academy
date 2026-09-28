<?php

namespace App\Http\Resources\Api\V1;

use App\CourseStatus;
use App\Models\Course;
use App\Models\Package;
use App\PackageStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Course|Package|null $product */
        $product = $this->whenLoaded('purchasable');
        $available = match (true) {
            $product instanceof Course => $product->status === CourseStatus::Published
                && $product->published_at !== null
                && $product->published_at->isPast(),
            $product instanceof Package => $product->status === PackageStatus::Published
                && $product->published_at !== null
                && $product->published_at->isPast(),
            default => false,
        };

        return [
            'id' => $this->id,
            'purchasable_type' => $this->purchasable_type,
            'purchasable_id' => $this->purchasable_id,
            'available' => $available,
            'product' => $product instanceof Course || $product instanceof Package ? [
                'id' => $product->id,
                'title' => $product->title,
                'slug' => $product->slug,
                'price' => $product->price,
                'access_duration_days' => $product->access_duration_days,
                'thumbnail' => $product->thumbnail,
            ] : null,
        ];
    }
}
