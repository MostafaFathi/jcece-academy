<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentFileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_staff_upload_download_and_delete_private_attachment_without_path_leakage(): void
    {
        Storage::fake('local');
        $this->authenticateAs(RoleName::ContentManager->value);
        $assignment = Assignment::factory()->create();

        $response = $this->post("/api/v1/admin/assignments/{$assignment->id}/attachments", [
            'file' => UploadedFile::fake()->create('brief.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.original_filename', 'brief.pdf')
            ->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.storage_disk');
        $attachment = AssignmentAttachment::findOrFail($response->json('data.id'));
        Storage::disk('local')->assertExists($attachment->storage_path);

        $this->get("/api/v1/admin/assignment-attachments/{$attachment->id}/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-disposition');
        $this->deleteJson("/api/v1/admin/assignments/{$assignment->id}/attachments/{$attachment->id}")->assertNoContent();
        Storage::disk('local')->assertMissing($attachment->storage_path);
    }

    public function test_returns_422_for_unsafe_attachment_type(): void
    {
        Storage::fake('local');
        $this->authenticateAs(RoleName::Admin->value);
        $assignment = Assignment::factory()->create();

        $this->post("/api/v1/admin/assignments/{$assignment->id}/attachments", [
            'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('assignment_attachments', 0);
    }

    public function test_student_cannot_upload_assignment_attachment(): void
    {
        Storage::fake('local');
        $this->authenticateAs(RoleName::Student->value);
        $assignment = Assignment::factory()->create();

        $this->post("/api/v1/admin/assignments/{$assignment->id}/attachments", [
            'file' => UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertForbidden();

        $this->assertDatabaseCount('assignment_attachments', 0);
    }

    private function authenticateAs(string $role): User
    {
        config()->set('jcec.assignments.file_disk', 'local');
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
