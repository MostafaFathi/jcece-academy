<?php

namespace Tests\Feature\Api\V1\Me;

use App\AssignmentSubmissionStatus;
use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentSubmissionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_student_creates_one_active_draft_edits_it_and_snapshots_assignment_configuration(): void
    {
        [$user, $assignment] = $this->accessibleAssignment([
            'title' => 'Original Assignment',
            'instructions' => 'Original requirements',
            'maximum_score' => '80.00',
            'passing_score' => '40.00',
            'submission_type' => AssignmentSubmissionType::Text,
            'due_at' => now()->addDay(),
        ]);

        $first = $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertCreated()
            ->assertJsonPath('data.attempt_number', 1)
            ->assertJsonPath('data.assignment_title', 'Original Assignment');
        $submissionId = $first->json('data.id');

        $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertCreated()->assertJsonPath('data.id', $submissionId);
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'My final explanation.'])
            ->assertOk()->assertJsonPath('data.text_answer', 'My final explanation.');

        $this->assertDatabaseCount('assignment_submissions', 1);
        $this->assertDatabaseHas('assignment_submissions', [
            'id' => $submissionId,
            'user_id' => $user->id,
            'assignment_title' => 'Original Assignment',
            'maximum_score' => 80,
            'submission_type' => AssignmentSubmissionType::Text->value,
        ]);
    }

    public function test_text_submission_requires_content_and_becomes_immutable_after_submit(): void
    {
        [, $assignment] = $this->accessibleAssignment(['submission_type' => AssignmentSubmissionType::Text]);
        $submissionId = $this->startDraft($assignment);

        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('text_answer');
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Completed answer'])->assertOk();
        $submitted = $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertOk()->assertJsonPath('data.status', AssignmentSubmissionStatus::Submitted->value)
            ->assertJsonMissingPath('data.score')
            ->assertJsonMissingPath('data.feedback');
        $submittedAt = $submitted->json('data.submitted_at');

        $this->travel(5)->minutes();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertOk()->assertJsonPath('data.submitted_at', $submittedAt);
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Changed'])
            ->assertUnprocessable()->assertJsonValidationErrors('submission');

        $this->assertSame('Completed answer', AssignmentSubmission::findOrFail($submissionId)->text_answer);
    }

    public function test_max_attempts_are_enforced_after_finalized_submission(): void
    {
        [, $assignment] = $this->accessibleAssignment(['max_attempts' => 1]);
        $submissionId = $this->startDraft($assignment);
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Answer'])->assertOk();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")->assertOk();

        $this->postJson("/api/v1/me/assignments/{$assignment->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('assignment');
    }

    public function test_deadline_is_enforced_server_side_and_late_status_is_derived(): void
    {
        $this->travelTo('2026-09-24 10:00:00');
        [, $closed] = $this->accessibleAssignment(['due_at' => now()->subMinute(), 'allow_late_submissions' => false]);

        $this->postJson("/api/v1/me/assignments/{$closed->id}/submissions")
            ->assertUnprocessable()->assertJsonValidationErrors('assignment');

        [, $lateAllowed] = $this->accessibleAssignment(['due_at' => now()->subMinute(), 'allow_late_submissions' => true]);
        $submissionId = $this->startDraft($lateAllowed);
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Late answer'])->assertOk();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertOk()->assertJsonPath('data.is_late', true);
    }

    public function test_student_cannot_view_or_change_another_students_submission(): void
    {
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/me/assignment-submissions/{$submissionId}")->assertForbidden();
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Stolen'])
            ->assertForbidden();
    }

    public function test_revoked_course_access_blocks_draft_changes_and_submission(): void
    {
        [, $assignment] = $this->accessibleAssignment();
        $submissionId = $this->startDraft($assignment);
        EnrollmentAccessGrant::query()->update(['revoked_at' => now()]);

        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Blocked'])
            ->assertForbidden();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")
            ->assertForbidden();

        $this->assertNull(AssignmentSubmission::findOrFail($submissionId)->submitted_at);
    }

    public function test_assignment_edits_do_not_change_submitted_snapshot_or_text(): void
    {
        [, $assignment] = $this->accessibleAssignment(['title' => 'Original', 'maximum_score' => '100.00']);
        $submissionId = $this->startDraft($assignment);
        $this->patchJson("/api/v1/me/assignment-submissions/{$submissionId}", ['text_answer' => 'Preserved'])->assertOk();
        $this->postJson("/api/v1/me/assignment-submissions/{$submissionId}/submit")->assertOk();
        $assignment->update(['title' => 'Changed', 'instructions' => 'Changed', 'maximum_score' => '200.00']);
        $assignment->delete();

        $this->getJson("/api/v1/me/assignment-submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('data.assignment_title', 'Original')
            ->assertJsonPath('data.maximum_score', '100.00')
            ->assertJsonPath('data.text_answer', 'Preserved');
        $this->assertSoftDeleted($assignment);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $submissionId]);
    }

    /** @param array<string, mixed> $attributes
     * @return array{User, Assignment}
     */
    private function accessibleAssignment(array $attributes = []): array
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $assignment = Assignment::factory()->published()->create($attributes);
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
