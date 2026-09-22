<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResourceResource extends JsonResource
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
            'type' => $this->type,
            'file_path' => $this->file_path,
            'external_url' => $this->external_url,
            'is_downloadable' => $this->is_downloadable,
            'sort_order' => $this->sort_order,
        ];
    }
}
