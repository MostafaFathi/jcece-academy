<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentGradingEventResource extends JsonResource
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
            'action' => $this->action->value,
            'reviewer_id' => $this->reviewer_id,
            'previous_score' => $this->previous_score,
            'previous_passed' => $this->previous_passed,
            'previous_feedback' => $this->previous_feedback,
            'score' => $this->score,
            'passed' => $this->passed,
            'feedback' => $this->feedback,
            'reason' => $this->reason,
            'created_at' => $this->created_at,
        ];
    }
}
