<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\InstructorProfile;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class AdminInstructorApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication_for_instructor_routes(): void
    {
        $this->getJson('/api/v1/admin/instructors')->assertUnauthorized();
        $this->postJson('/api/v1/admin/instructors', [])->assertUnauthorized();
        $this->getJson('/api/v1/admin/instructors/1')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/instructors/1', [])->assertUnauthorized();
    }

    public function test_admin_lists_and_searches_instructors_with_paginated_safe_profiles(): void
    {
        $this->actingAsAdmin();
        $instructor = User::factory()->create(['name' => 'Unique Teacher']);
        $instructor->assignRole(RoleName::Instructor->value);
        InstructorProfile::factory()->for($instructor)->create(['job_title' => 'Safety Trainer']);
        User::factory()->create(['name' => 'Unique Student'])->assignRole(RoleName::Student->value);

        $this->getJson('/api/v1/admin/instructors?search=Unique&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $instructor->id)
            ->assertJsonPath('data.0.profile.job_title', 'Safety Trainer')
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token');
        $this->getJson("/api/v1/admin/instructors/{$instructor->id}")
            ->assertOk()->assertJsonPath('data.email', $instructor->email);
    }

    public function test_admin_creates_instructor_account_role_and_profile_atomically(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/instructors', $this->newInstructorPayload())
            ->assertCreated()->assertJsonPath('data.name', 'New Trainer')
            ->assertJsonPath('data.profile.job_title', 'Safety Trainer')->assertJsonMissingPath('data.password');

        $instructor = User::where('email', 'new.trainer@example.test')->firstOrFail();
        $this->assertTrue($instructor->hasRole(RoleName::Instructor->value));
        $this->assertTrue(Hash::check('StrongPass123', $instructor->password));
        $this->assertDatabaseHas('instructor_profiles', ['user_id' => $instructor->id, 'job_title' => 'Safety Trainer']);
    }

    public function test_instructor_creation_rolls_back_user_and_role_when_profile_write_fails(): void
    {
        $this->actingAsAdmin();
        InstructorProfile::creating(static function (): void {
            throw new RuntimeException('Simulated profile failure');
        });

        try {
            $this->postJson('/api/v1/admin/instructors', $this->newInstructorPayload())->assertInternalServerError();
        } finally {
            InstructorProfile::flushEventListeners();
        }

        $this->assertDatabaseMissing('users', ['email' => 'new.trainer@example.test']);
    }

    public function test_returns_422_for_duplicate_instructor_email_without_creating_profile(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['email' => 'new.trainer@example.test']);

        $this->postJson('/api/v1/admin/instructors', $this->newInstructorPayload())
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertDatabaseCount('instructor_profiles', 0);
    }

    public function test_content_manager_can_update_profile_but_cannot_create_or_edit_account_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $instructor->instructorProfile()->create(['job_title' => 'Old title']);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/instructors')->assertOk()->assertJsonMissingPath('data.0.email');
        $this->postJson('/api/v1/admin/instructors', $this->newInstructorPayload())->assertForbidden();
        $this->patchJson("/api/v1/admin/instructors/{$instructor->id}", ['job_title' => 'New title'])
            ->assertOk()->assertJsonPath('data.profile.job_title', 'New title');
        $this->patchJson("/api/v1/admin/instructors/{$instructor->id}", ['name' => 'Escalated'])->assertForbidden();
        $this->assertSame($instructor->name, $instructor->fresh()->name);
    }

    public function test_admin_updates_account_and_profile_in_one_request_and_repairs_missing_profile(): void
    {
        $this->actingAsAdmin();
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);

        $this->patchJson("/api/v1/admin/instructors/{$instructor->id}", [
            'name' => 'Updated Trainer', 'job_title' => 'Technical Trainer', 'specialties' => ['Safety'],
        ])->assertOk()->assertJsonPath('data.name', 'Updated Trainer')
            ->assertJsonPath('data.profile.specialties.0', 'Safety');
        $this->assertDatabaseHas('instructor_profiles', ['user_id' => $instructor->id, 'job_title' => 'Technical Trainer']);
    }

    public function test_instructor_password_change_requires_management_permission_and_never_returns_a_hash(): void
    {
        $this->actingAsAdmin();
        $instructor = User::factory()->create(['password' => 'OriginalPass123']);
        $instructor->assignRole(RoleName::Instructor->value);

        $this->patchJson("/api/v1/admin/instructors/{$instructor->id}", [
            'password' => 'ChangedPass123', 'password_confirmation' => 'ChangedPass123',
        ])->assertOk()->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('ChangedPass123', $instructor->fresh()->password));

        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        Sanctum::actingAs($manager);
        $this->patchJson("/api/v1/admin/instructors/{$instructor->id}", [
            'password' => 'DeniedPass123', 'password_confirmation' => 'DeniedPass123',
        ])->assertForbidden();
        $this->assertTrue(Hash::check('ChangedPass123', $instructor->fresh()->password));
    }

    public function test_non_instructor_detail_is_404_and_student_cannot_manage_instructors(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->getJson("/api/v1/admin/instructors/{$student->id}")->assertNotFound();

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/instructors')->assertForbidden();
        $this->postJson('/api/v1/admin/instructors', $this->newInstructorPayload())->assertForbidden();
    }

    /** @return array<string, mixed> */
    private function newInstructorPayload(): array
    {
        return [
            'name' => 'New Trainer', 'email' => 'new.trainer@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'job_title' => 'Safety Trainer', 'specialties' => ['Safety', 'Training'],
        ];
    }

    private function actingAsAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        return $admin;
    }
}
