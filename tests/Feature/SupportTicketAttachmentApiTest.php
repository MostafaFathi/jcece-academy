<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\RoleName;
use App\Services\SupportTicketMessageService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketAttachmentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_uploads_private_attachment_without_raw_path_leakage(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        Sanctum::actingAs($student);

        $response = $this->post('/api/v1/me/support-tickets', [
            'subject' => 'Screenshot', 'category' => 'technical', 'body' => 'Attached screenshot',
            'attachments' => [UploadedFile::fake()->image('problem.jpg')],
        ], ['Accept' => 'application/json'])->assertCreated();
        $ticket = SupportTicket::query()->findOrFail($response->json('data.id'));
        $attachment = $ticket->visibleMessages()->firstOrFail()->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->storage_path);

        $messages = $this->getJson("/api/v1/me/support-tickets/{$ticket->id}/messages")->assertOk();
        $this->assertStringNotContainsString('storage_path', $messages->getContent());
        $this->assertStringNotContainsString($attachment->storage_path, $messages->getContent());
        $this->get("/api/v1/me/support-ticket-attachments/{$attachment->id}/download", ['Accept' => 'application/json'])->assertOk();
    }

    public function test_upload_type_size_and_count_are_validated(): void
    {
        Storage::fake('local');
        config(['jcec.support.attachment_max_count' => 1, 'jcec.support.attachment_max_kilobytes' => 5]);
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $base = ['subject' => 'Files', 'category' => 'general', 'body' => 'Files attached'];

        $this->post('/api/v1/me/support-tickets', $base + ['attachments' => [UploadedFile::fake()->create('bad.exe', 1, 'application/x-msdownload')]], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post('/api/v1/me/support-tickets', $base + ['attachments' => [UploadedFile::fake()->create('large.pdf', 10, 'application/pdf')]], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
        $this->post('/api/v1/me/support-tickets', $base + ['attachments' => [UploadedFile::fake()->create('one.pdf', 1, 'application/pdf'), UploadedFile::fake()->create('two.pdf', 1, 'application/pdf')]], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachments');
        $this->assertDatabaseCount('support_ticket_attachments', 0);
    }

    public function test_cross_user_and_internal_attachment_downloads_return_not_found(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ticket = SupportTicket::factory()->for($owner)->create();
        $public = SupportTicketMessage::factory()->for($ticket, 'ticket')->for($owner)->create();
        $internal = SupportTicketMessage::factory()->internal()->for($ticket, 'ticket')->create();
        $publicFile = SupportTicketAttachment::factory()->for($public, 'message')->create();
        $internalFile = SupportTicketAttachment::factory()->for($internal, 'message')->create();
        Storage::disk('local')->put($publicFile->storage_path, 'public');
        Storage::disk('local')->put($internalFile->storage_path, 'secret');

        Sanctum::actingAs($other);
        $this->getJson("/api/v1/me/support-ticket-attachments/{$publicFile->id}/download")->assertNotFound();
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/me/support-ticket-attachments/{$internalFile->id}/download")->assertNotFound();
    }

    public function test_authorized_staff_downloads_internal_attachment(): void
    {
        Storage::fake('local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::SalesSupport->value);
        $message = SupportTicketMessage::factory()->internal()->create();
        $attachment = SupportTicketAttachment::factory()->for($message, 'message')->create();
        Storage::disk('local')->put($attachment->storage_path, 'secret');
        Sanctum::actingAs($staff);

        $this->get("/api/v1/admin/support-ticket-attachments/{$attachment->id}/download", ['Accept' => 'application/json'])->assertOk();
    }

    public function test_attachment_uses_readable_temporary_path_when_realpath_is_unavailable(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $ticket = SupportTicket::factory()->for($student)->create();
        $temporaryFile = UploadedFile::fake()->create('problem.pdf', 1, 'application/pdf');
        $attachment = new class($temporaryFile->getPathname()) extends UploadedFile
        {
            public function __construct(string $path)
            {
                parent::__construct($path, 'problem.pdf', 'application/pdf', null, true);
            }

            public function getRealPath(): string|false
            {
                return false;
            }
        };

        $message = app(SupportTicketMessageService::class)->post($student, $ticket, 'Attached file', false, [$attachment]);

        Storage::disk('local')->assertExists($message->attachments->firstOrFail()->storage_path);
    }

    public function test_missing_temporary_attachment_returns_validation_error_without_partial_message(): void
    {
        Storage::fake('local');
        $student = User::factory()->create();
        $ticket = SupportTicket::factory()->for($student)->create();
        Storage::disk('local')->put('missing-upload.pdf', 'temporary content');
        $attachment = new UploadedFile(Storage::disk('local')->path('missing-upload.pdf'), 'missing.pdf', 'application/pdf', null, true);
        Storage::disk('local')->delete('missing-upload.pdf');

        try {
            app(SupportTicketMessageService::class)->post($student, $ticket, 'Attached file', false, [$attachment]);
            $this->fail('An unavailable upload must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attachments', $exception->errors());
        }

        $this->assertDatabaseCount('support_ticket_messages', 0);
        $this->assertDatabaseCount('support_ticket_attachments', 0);
    }
}
