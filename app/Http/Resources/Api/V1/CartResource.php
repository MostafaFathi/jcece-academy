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
            'currency' => app(CommerceCatalogService::class)->currency(),
        ];
    }
}
