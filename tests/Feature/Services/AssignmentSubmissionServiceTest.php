<?php

namespace Tests\Feature\Services;

use App\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\Services\AssignmentFileService;
use App\Services\AssignmentSubmissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AssignmentSubmissionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_starting_twice_returns_same_active_draft(): void
    {
        [$user, $assignment] = $this->scenario();
        $service = app(AssignmentSubmissionService::class);

        $first = $service->startDraft($user, $assignment);
        $second = $service->startDraft($user, $assignment);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $second->attempt_number);
        $this->assertSame(AssignmentSubmissionStatus::Draft, $second->status);
        $this->assertDatabaseCount('assignment_submissions', 1);
    }

    public function test_database_unique_key_prevents_simultaneous_active_drafts(): void
    {
        [$user, $assignment, $enrollment] = $this->scenario();
        $service = app(AssignmentSubmissionService::class);
        $draft = $service->startDraft($user, $assignment);

        $this->expectException(QueryException::class);
        AssignmentSubmission::factory()->for($assignment)->for($user)->for($enrollment)->create([
            'attempt_number' => 2,
            'active_key' => $draft->active_key,
        ]);
    }

    public function test_stored_file_is_cleaned_up_when_database_write_fails(): void
    {
        Storage::fake('local');
        config()->set('jcec.assignments.file_disk', 'local');
        [$user, $assignment] = $this->scenario();
        $submission = app(AssignmentSubmissionService::class)->startDraft($user, $assignment);
        $eventName = 'eloquent.creating: '.AssignmentSubmissionFile::class;
        Event::listen($eventName, fn (): never => throw new RuntimeException('Simulated database failure.'));
        $exception = null;

        try {
            app(AssignmentFileService::class)->storeSubmissionFiles($user, $submission, [
                UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
            ]);
        } catch (RuntimeException $caught) {
            $exception = $caught;
        } finally {
            Event::forget($eventName);
        }

        $this->assertNotNull($exception);
        $this->assertDatabaseCount('assignment_submission_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /** @return array{User, Assignment, Enrollment} */
    private function scenario(): array
    {
        $user = User::factory()->create();
        $assignment = Assignment::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($assignment->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        return [$user, $assignment, $enrollment];
    }
}
