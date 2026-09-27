<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminSupportTicketMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'is_internal' => $this->is_internal,
            'author' => $this->whenLoaded('user', fn (): array => ['id' => $this->user->id, 'name' => $this->user->name]),
            'body' => $this->body,
            'attachments' => $this->whenLoaded('attachments', fn () => $this->attachments->map(fn ($attachment): array => [
                'id' => $attachment->id, 'filename' => $attachment->original_filename, 'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size, 'download_url' => route('api.v1.admin.support-ticket-attachments.download', $attachment),
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
