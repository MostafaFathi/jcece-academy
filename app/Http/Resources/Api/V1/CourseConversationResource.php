<?php

namespace App\Http\Resources\Api\V1;

use App\Services\CourseMessagingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(CourseMessagingService::class)->describe($request->user(), $this->resource);
    }
}
