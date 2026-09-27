<?php

namespace Tests\Feature\Api\V1\Admin;

use App\AssignmentGradingAction;
use App\AssignmentSubmissionStatus;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignmentGradingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('staffRoles')]
    public function test_authorized_staff_grade_submission_and_passing_boundary_is_inclusive(string $role): void
    {
        $this->travelTo('2026-09-24 12:00:00');
        [$submission] = $this->submittedWork();
        $reviewer = $this->authenticateAs($role);

        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/grade", [
            'score' => 60,
            'feedback' => 'Meets the requirements.',
        ])->assertOk()
            ->assertJsonPath('data.status', AssignmentSubmissionStatus::Graded->value)
            ->assertJsonPath('data.score', '60.00')
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.graded_by', $reviewer->id);

        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submission->id,
            'graded_by' => $reviewer->id,
            'graded_at' => '2026-09-24 12:00:00',
            'passed' => true,
        ]);
        $this->assertDatabaseHas('assignment_grading_events', [
            'assignment_submission_id' => $submission->id,
            'action' => AssignmentGradingAction::Graded->value,
            'reviewer_id' => $reviewer->id,
        ]);
    }

    public function test_assigned_instructor_can_grade_but_unrelated_instructor_is_forbidden(): void
    {
        $instructor = User::factory()->create();
        [$submission] = $this->submittedWork(Course::factory()->for($instructor, 'instructor')->create());
        $this->authenticateExistingAs($instructor, RoleName::Instructor->value);

        $this->getJson("/api/v1/admin/assignments/{$submission->assignment_id}/submissions")
            ->assertOk()->assertJsonPath('data.0.id', $submission->id);
        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/grade", ['score' => 70])
            ->assertOk();

        [$foreignSubmission] = $this->submittedWork();
        $this->getJson("/api/v1/admin/assignments/{$foreignSubmission->assignment_id}/submissions")
            ->assertForbidden();
        $this->postJson("/api/v1/admin/assignment-submissions/{$foreignSubmission->id}/grade", ['score' => 70])
            ->assertForbidden();
    }

    public function test_score_above_snapshotted_maximum_returns_422(): void
    {
        [$submission] = $this->submittedWork();
        $this->authenticateAs(RoleName::Admin->value);

        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/grade", ['score' => 100.01])
            ->assertUnprocessable()->assertJsonValidationErrors('score');

        $this->assertSame(AssignmentSubmissionStatus::Submitted, $submission->fresh()->status);
        $this->assertDatabaseCount('assignment_grading_events', 0);
    }

    public function test_student_cannot_use_administrative_submission_or_file_resources(): void
    {
        [$submission, $assignment, $student] = $this->submittedWork();
        $file = AssignmentSubmissionFile::factory()->for($submission, 'submission')->create();
        Sanctum::actingAs($student);

        $this->getJson("/api/v1/admin/assignments/{$assignment->id}/submissions/{$submission->id}")
            ->assertForbidden();
        $this->getJson("/api/v1/admin/assignment-submission-files/{$file->id}/download")
            ->assertForbidden();
    }

    public function test_explicit_grade_correction_preserves_audit_history(): void
    {
        [$submission] = $this->submittedWork();
        $reviewer = $this->authenticateAs(RoleName::ContentManager->value);
        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/grade", [
            'score' => 55, 'feedback' => 'Initial review.',
        ])->assertOk()->assertJsonPath('data.passed', false);

        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/grade-corrections", [
            'score' => 75,
            'feedback' => 'Corrected review.',
            'reason' => 'A rubric item was missed.',
        ])->assertOk()
            ->assertJsonPath('data.passed', true)
            ->assertJsonPath('data.grading_history.1.action', AssignmentGradingAction::Corrected->value)
            ->assertJsonPath('data.grading_history.1.previous_score', '55.00')
            ->assertJsonPath('data.grading_history.1.reason', 'A rubric item was missed.');

        $this->assertDatabaseHas('assignment_grading_events', [
            'assignment_submission_id' => $submission->id,
            'reviewer_id' => $reviewer->id,
            'action' => AssignmentGradingAction::Corrected->value,
            'previous_score' => 55,
            'score' => 75,
        ]);
    }

    public function test_revision_request_preserves_attempt_and_student_creates_new_numbered_attempt(): void
    {
        [$submission, $assignment, $student] = $this->submittedWork();
        $assignment->update(['max_attempts' => 2]);
        $this->authenticateAs(RoleName::Admin->value);

        $this->postJson("/api/v1/admin/assignment-submissions/{$submission->id}/revision", [
            'feedback' => 'Please add the missing calculations.',
        ])->assertOk()->assertJsonPath('data.status', AssignmentSubmissionStatus::RevisionRequested->value);

        Sanctum::actingAs($student);
        $this->getJson("/api/v1/me/assignment-submissions/{$submission->id}")
            ->assertOk()->assertJsonPath('data.feedback', 'Please add the missing calculations.')
            ->assertJsonMissingPath('data.grading_history');
        $second = $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertCreated()->assertJsonPath('data.attempt_number', 2);
        $secondId = $second->json('data.id');
        $this->patchJson("/api/v1/me/assignment-submissions/{$secondId}", ['text_answer' => 'Revised work.'])->assertOk();
        $this->postJson("/api/v1/me/assignment-submissions/{$secondId}/submit")->assertOk();
        $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('assignment');

        $this->assertDatabaseCount('assignment_submissions', 2);
    }

    /** @return array{AssignmentSubmission, Assignment, User} */
    private function submittedWork(?Course $course = null): array
    {
        $student = User::factory()->create();
        $assignment = Assignment::factory()->published()->for($course ?? Course::factory()->create())->create([
            'maximum_score' => '100.00',
            'passing_score' => '60.00',
        ]);
        $enrollment = Enrollment::factory()->for($student)->for($assignment->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();
        $submission = AssignmentSubmission::factory()->submitted()->for($assignment)->for($enrollment)->for($student)->create([
            'assignment_title' => $assignment->title,
            'maximum_score' => '100.00',
            'passing_score' => '60.00',
            'submission_type' => $assignment->submission_type,
            'text_answer' => 'Student work.',
        ]);

        return [$submission, $assignment, $student];
    }

    /** @return array<string, array{string}> */
    public static function staffRoles(): array
    {
        return ['admin' => [RoleName::Admin->value], 'content manager' => [RoleName::ContentManager->value]];
    }

    private function authenticateAs(string $role): User
    {
        return $this->authenticateExistingAs(User::factory()->create(), $role);
    }

    private function authenticateExistingAs(User $user, string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
