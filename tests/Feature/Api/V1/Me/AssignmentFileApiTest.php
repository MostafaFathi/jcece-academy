<?php

namespace Tests\Feature\Api\V1\Me;

use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmissionFile;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentFileApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_uploads_private_files_submits_and_cannot_remove_finalized_file(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);

        $response = $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [UploadedFile::fake()->create('work.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk()
            ->assertJsonPath('data.files.0.original_filename', 'work.pdf')
            ->assertJsonMissingPath('data.files.0.storage_path');
        $file = AssignmentSubmissionFile::findOrFail($response->json('data.files.0.id'));
        Storage::disk('local')->assertExists($file->storage_path);

        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertOk()->assertJsonPath('data.is_late', false);
        $this->deleteJson("/api/v1/me/assignment-submissions/{$submissionId}/files/{$file->id}")
            ->assertUnprocessable()->assertJsonValidationErrors('submission');
        Storage::disk('local')->assertExists($file->storage_path);
    }

    public function test_file_submission_requires_file_and_rejects_unsafe_upload(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);

        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('files');
        $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('files.0');

        $this->assertDatabaseCount('assignment_submission_files', 0);
    }

    public function test_text_and_file_submission_requires_both_contents(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->accessibleAssignment(AssignmentSubmissionType::TextAndFile);
        $submissionId = $this->startDraft($assignment);
        $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [UploadedFile::fake()->create('work.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('text_answer');
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Written explanation.'])->assertOk();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")->assertOk();
    }

    public function test_submission_file_is_downloadable_only_by_owner(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);
        $response = $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [UploadedFile::fake()->create('private.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertOk();
        $fileId = $response->json('data.files.0.id');

        $this->get("/api/v1/me/assignment-submission-files/{$fileId}/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-disposition');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/me/assignment-submission-files/{$fileId}/download")->assertNotFound();
    }

    public function test_total_submission_file_count_is_enforced_transactionally(): void
    {
        Storage::fake('local');
        config()->set('jcec.assignments.submission_file_max_count', 2);
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);
        $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [
                UploadedFile::fake()->create('one.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('two.pdf', 10, 'application/pdf'),
            ],
        ], ['Accept' => 'application/json'])->assertOk();

        $this->post("/api/v1/me/assignment-submissions/{$submissionId}/files", [
            'files' => [UploadedFile::fake()->create('three.pdf', 10, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertDatabaseCount('assignment_submission_files', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
    }

    public function test_student_with_course_access_downloads_assignment_attachment_without_raw_path(): void
    {
        Storage::fake('local');
        [, $assignment] = $this->accessibleAssignment();
        $attachment = $assignment->attachments()->create([
            'original_filename' => 'guide.pdf', 'storage_disk' => 'local',
            'storage_path' => "assignment-attachments/{$assignment->id}/guide.pdf",
            'mime_type' => 'application/pdf', 'file_size' => 5,
        ]);
        Storage::disk('local')->put($attachment->storage_path, 'guide');

        $this->getJson("/api/v1/me/assignments/{$assignment->id}")
            ->assertOk()->assertJsonMissingPath('data.attachments.0.storage_path');
        $this->get("/api/v1/me/assignment-attachments/{$attachment->id}/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-disposition');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/me/assignment-attachments/{$attachment->id}/download")->assertForbidden();
    }

    /** @return array{User, Assignment} */
    private function accessibleAssignment(AssignmentSubmissionType $type = AssignmentSubmissionType::File): array
    {
        config()->set('jcec.assignments.file_disk', 'local');
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $assignment = Assignment::factory()->published()->create(['submission_type' => $type]);
        $enrollment = Enrollment::factory()->for($user)->for($assignment->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        return [$user, $assignment];
    }

    private function startDraft(Assignment $assignment): int
    {
        return $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertCreated()->json('data.id');
    }
}
