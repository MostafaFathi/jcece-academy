<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication(): void
    {
        $this->getJson('/api/v1/admin/dashboard-summary')->assertUnauthorized();
    }

    public function test_admin_receives_only_real_record_counts(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $category = Category::factory()->create();
        Course::factory()->create(['category_id' => $category->id, 'instructor_id' => $instructor->id, 'status' => 'draft']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard-summary')
            ->assertOk()->assertJsonPath('data.total_categories', 1)
            ->assertJsonPath('data.total_courses', 1)->assertJsonPath('data.draft_courses', 1)
            ->assertJsonPath('data.published_courses', 0)
            ->assertJsonPath('data.total_users', 3)->assertJsonPath('data.total_students', 1)
            ->assertJsonPath('data.total_instructors', 1);
    }

    public function test_content_manager_receives_course_and_category_counts_without_user_totals(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/dashboard-summary')
            ->assertOk()->assertJsonPath('data.total_courses', 0)
            ->assertJsonPath('data.total_categories', 0)
            ->assertJsonMissingPath('data.total_users')->assertJsonMissingPath('data.total_students');
    }
}
