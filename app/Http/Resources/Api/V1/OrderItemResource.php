<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'purchasable_type' => $this->purchasable_type,
            'purchasable_id' => $this->purchasable_id,
            'title' => $this->title,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'discount_amount' => $this->discount_amount,
            'promotional_discount_amount' => $this->promotional_discount_amount,
            'coupon_discount_amount' => $this->coupon_discount_amount,
            'total' => $this->total,
            'access_duration_days' => $this->access_duration_days,
            'package_courses' => OrderItemPackageCourseResource::collection($this->whenLoaded('packageCourses')),
        ];
    }
}
