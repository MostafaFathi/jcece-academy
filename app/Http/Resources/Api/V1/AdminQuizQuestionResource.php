<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminQuizQuestionResource extends JsonResource
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
            'quiz_id' => $this->quiz_id,
            'type' => $this->type->value,
            'question_text' => $this->question_text,
            'explanation' => $this->explanation,
            'points' => $this->points,
            'sort_order' => $this->sort_order,
            'options' => AdminQuizOptionResource::collection($this->whenLoaded('options')),
        ];
    }
}
