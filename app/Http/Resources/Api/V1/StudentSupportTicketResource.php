<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentSupportTicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'ticket_number' => $this->ticket_number, 'subject' => $this->subject,
            'category' => $this->category->value, 'priority' => $this->priority->value, 'status' => $this->status->value,
            'related_order' => $this->whenLoaded('relatedOrder', fn (): ?array => $this->relatedOrder === null ? null : ['id' => $this->relatedOrder->id, 'order_number' => $this->relatedOrder->order_number]),
            'related_course' => $this->whenLoaded('relatedCourse', fn (): ?array => $this->relatedCourse === null ? null : ['id' => $this->relatedCourse->id, 'title' => $this->relatedCourse->title, 'slug' => $this->relatedCourse->slug]),
            'last_reply_at' => $this->last_reply_at, 'resolved_at' => $this->resolved_at, 'closed_at' => $this->closed_at,
            'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
