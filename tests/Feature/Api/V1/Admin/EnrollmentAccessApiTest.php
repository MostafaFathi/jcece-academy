<?php

namespace Tests\Feature\Api\V1\Admin;

use App\AccessGrantSource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EnrollmentAccessApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_grant_creates_enrollment_and_new_admin_grant(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => 30]);

        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access")
            ->assertCreated()
            ->assertJsonPath('data.source_type', AccessGrantSource::Admin->value);

        $enrollment = Enrollment::query()->whereBelongsTo($student)->whereBelongsTo($course)->firstOrFail();
        $grant = $enrollment->accessGrants()->sole();
        $this->assertSame('2026-10-31 12:00:00', $grant->access_expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_additional_grant_reuses_enrollment_and_preserves_existing_grants(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => 10]);
        $enrollment = Enrollment::factory()->for($student)->for($course)->create();
        $existingGrant = EnrollmentAccessGrant::factory()->for($enrollment)->create([
            'access_expires_at' => now()->addDays(5),
        ]);

        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access")
            ->assertCreated();

        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseCount('enrollment_access_grants', 2);
        $this->assertSame('2026-10-06 12:00:00', $existingGrant->fresh()->access_expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_explicit_expiration_overrides_course_duration(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => 30]);

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access", [
            'access_starts_at' => '2026-11-01 10:00:00',
            'access_expires_at' => '2027-02-01 10:00:00',
        ])->assertCreated();

        $grant = EnrollmentAccessGrant::findOrFail($response->json('data.id'));
        $this->assertSame('2027-02-01 10:00:00', $grant->access_expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_lifetime_course_creates_lifetime_grant(): void
    {
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => null]);

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access")
            ->assertCreated()
            ->assertJsonPath('data.access_expires_at', null);

        $this->assertNull(EnrollmentAccessGrant::findOrFail($response->json('data.id'))->access_expires_at);
    }

    public function test_explicit_null_expiration_creates_lifetime_grant_for_finite_course(): void
    {
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => 30]);

        $response = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access", [
            'access_expires_at' => null,
        ])->assertCreated()
            ->assertJsonPath('data.access_expires_at', null);

        $this->assertNull(EnrollmentAccessGrant::findOrFail($response->json('data.id'))->access_expires_at);
    }

    public function test_changing_course_duration_does_not_change_existing_grant_expiration(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create(['access_duration_days' => 30]);
        $response = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access")->assertCreated();

        $course->update(['access_duration_days' => 365]);

        $grant = EnrollmentAccessGrant::findOrFail($response->json('data.id'));
        $this->assertSame('2026-10-31 12:00:00', $grant->access_expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_expiration_must_be_after_start(): void
    {
        $this->authenticateAs(RoleName::Admin);
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access", [
            'access_starts_at' => '2026-11-02 10:00:00',
            'access_expires_at' => '2026-11-01 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('access_expires_at');

        $this->assertDatabaseCount('enrollments', 0);
        $this->assertDatabaseCount('enrollment_access_grants', 0);
    }

    public function test_student_cannot_manually_grant_access(): void
    {
        $this->authenticateAs(RoleName::Student);
        $student = User::factory()->create();
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/access")
            ->assertForbidden();

        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_admin_revokes_one_grant_without_deleting_it_or_revoking_another(): void
    {
        $admin = $this->authenticateAs(RoleName::Admin);
        $enrollment = Enrollment::factory()->create();
        $grant = EnrollmentAccessGrant::factory()->for($enrollment)->create();
        $otherGrant = EnrollmentAccessGrant::factory()->for($enrollment)->create();

        $this->deleteJson("/api/v1/admin/access-grants/{$grant->id}", [
            'revocation_reason' => 'Duplicate manual access.',
        ])->assertOk()
            ->assertJsonPath('data.revocation_reason', 'Duplicate manual access.');

        $this->assertDatabaseHas('enrollment_access_grants', [
            'id' => $grant->id,
            'revoked_by' => $admin->id,
            'revocation_reason' => 'Duplicate manual access.',
        ]);
        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->assertNull($otherGrant->fresh()->revoked_at);
        $this->assertDatabaseCount('enrollment_access_grants', 2);
    }

    private function authenticateAs(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
