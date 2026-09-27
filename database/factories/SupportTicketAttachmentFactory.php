<?php

namespace Database\Factories;

use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketAttachment>
 */
class SupportTicketAttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['support_ticket_message_id' => SupportTicketMessage::factory(), 'original_filename' => 'document.pdf', 'storage_disk' => 'local', 'storage_path' => 'support-tickets/test/document.pdf', 'mime_type' => 'application/pdf', 'file_size' => 1024];
    }
}
