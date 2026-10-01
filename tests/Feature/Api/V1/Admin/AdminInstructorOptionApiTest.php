<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminInstructorOptionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/admin/instructor-options')->assertUnauthorized();
    }

    public function test_course_manager_can_search_paginated_valid_instructors_without_private_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        $instructor = User::factory()->create(['name' => 'Specific Trainer']);
        $instructor->assignRole(RoleName::Instructor->value);
        $instructor->instructorProfile()->create(['job_title' => 'Safety Trainer']);
        User::factory()->create(['name' => 'Specific Student'])->assignRole(RoleName::Student->value);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/instructor-options?search=Specific&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $instructor->id)
            ->assertJsonPath('data.0.job_title', 'Safety Trainer')
            ->assertJsonMissingPath('data.0.email')->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.roles');
    }

    public function test_instructor_directory_is_scoped_to_the_authenticated_instructor(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $editor = User::factory()->create();
        $editor->assignRole(RoleName::Instructor->value);
        $roleOnly = User::factory()->create(['name' => 'Role Only Trainer']);
        $roleOnly->assignRole(RoleName::Instructor->value);
        Sanctum::actingAs($editor);

        $this->getJson('/api/v1/admin/instructor-options?search=Role%20Only')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/admin/instructor-options?search='.$editor->name)
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $editor->id)
            ->assertJsonPath('data.0.job_title', null);
    }

    public function test_read_only_sales_support_cannot_use_course_assignment_directory(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        Sanctum::actingAs($support);

        $this->getJson('/api/v1/admin/instructor-options')->assertForbidden();
    }
}
