<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CommerceCatalogService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'items' => CartItemResource::collection($this->whenLoaded('items')),
            'item_count' => $this->whenLoaded('items', fn (): int => $this->items->count()),
            'estimated_total' => $this->estimated_total,
            'subtotal' => $this->pricing_summary['subtotal'] ?? '0.00',
            'promotional_savings' => $this->pricing_summary['promotional_savings'] ?? '0.00',
            'coupon_discount' => $this->pricing_summary['coupon_discount'] ?? '0.00',
            'discount_total' => $this->pricing_summary['discount_total'] ?? '0.00',
            'coupon_code' => $this->coupon_code,
            'coupon_error' => $this->coupon_error,
            'currency' => app(CommerceCatalogService::class)->currency(),
        ];
    }
}
