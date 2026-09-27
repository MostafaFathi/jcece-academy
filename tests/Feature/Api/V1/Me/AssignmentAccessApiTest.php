<?php

namespace Tests\Feature\Api\V1\Me;

use App\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentAccessApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/me/assignments')->assertUnauthorized();
    }

    public function test_student_with_effective_access_views_only_available_published_assignments(): void
    {
        $user = $this->authenticate();
        $available = Assignment::factory()->published()->create();
        Assignment::factory()->for($available->course)->create(['status' => AssignmentStatus::Draft]);
        Assignment::factory()->published()->for($available->course)->create(['available_from' => now()->addDay()]);
        $this->grantAccess($user, $available);

        $this->getJson('/api/v1/me/assignments')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonMissingPath('data.0.status');

        $this->getJson("/api/v1/me/assignments/{$available->id}")
            ->assertOk()->assertJsonMissingPath('data.deleted_at');
    }

    public function test_no_effective_grant_and_suspended_enrollment_are_forbidden(): void
    {
        $user = $this->authenticate();
        $assignment = Assignment::factory()->published()->create();

        $this->getJson("/api/v1/me/assignments/{$assignment->id}")->assertForbidden();

        $enrollment = Enrollment::factory()->suspended()->for($user)->for($assignment->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        $this->getJson("/api/v1/me/assignments/{$assignment->id}")->assertForbidden();
    }

    public function test_draft_archived_and_future_assignments_return_404(): void
    {
        $user = $this->authenticate();
        $assignments = [
            Assignment::factory()->create(),
            Assignment::factory()->create(['status' => AssignmentStatus::Archived]),
            Assignment::factory()->published()->create(['available_from' => now()->addHour()]),
        ];

        foreach ($assignments as $assignment) {
            $this->grantAccess($user, $assignment);
            $this->getJson("/api/v1/me/assignments/{$assignment->id}")->assertNotFound();
        }
    }

    private function authenticate(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function grantAccess(User $user, Assignment $assignment): Enrollment
    {
        $enrollment = Enrollment::factory()->for($user)->for($assignment->course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        return $enrollment;
    }
}
