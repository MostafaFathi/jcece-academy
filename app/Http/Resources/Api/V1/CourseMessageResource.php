<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CourseMessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(CourseMessagingService::class)->describeMessage($request->user(), $this->resource);
    }
}
