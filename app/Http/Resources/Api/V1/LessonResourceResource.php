<?php

namespace App\Http\Resources\Api\V1;

use App\Services\LessonResourceFileService;
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
            'file_reference_available' => LessonResourceFileService::hasSafeFile($this->resource),
            'download_available' => $this->is_downloadable && LessonResourceFileService::hasSafeFile($this->resource),
            'external_url' => LessonResourceFileService::safeExternalUrl($this->resource),
            'is_downloadable' => $this->is_downloadable,
            'sort_order' => $this->sort_order,
        ];
    }
}
