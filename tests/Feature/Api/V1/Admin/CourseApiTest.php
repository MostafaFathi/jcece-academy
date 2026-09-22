<?php

namespace Tests\Feature\Api\V1\Admin;

use App\CourseLevel;
use App\CourseStatus;
use App\Models\Category;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_authorized_admin_creates_course_with_ordered_details_and_returns_201(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $category = Category::factory()->create();
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/v1/admin/courses', [
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'Laravel APIs',
            'slug' => 'laravel-apis',
            'level' => CourseLevel::Beginner->value,
            'status' => CourseStatus::Published->value,
            'price' => 99.50,
            'access_duration_days' => 90,
            'learning_outcomes' => ['Design endpoints', 'Test endpoints'],
            'requirements' => ['Basic PHP'],
            'target_audiences' => ['Backend developers'],
            'required_tools' => ['PHP 8.4'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'laravel-apis')
            ->assertJsonPath('data.learning_outcomes.1.sort_order', 1);
        $this->assertDatabaseHas('courses', [
            'slug' => 'laravel-apis',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('course_learning_outcomes', ['outcome' => 'Test endpoints', 'sort_order' => 1]);
    }

    public function test_authenticated_user_without_permission_cannot_create_course(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $category = Category::factory()->create();
        Sanctum::actingAs($student);

        $this->postJson('/api/v1/admin/courses', $this->validPayload($category, $instructor))
            ->assertForbidden();

        $this->assertDatabaseMissing('courses', ['slug' => 'new-course']);
    }

    public function test_course_create_returns_422_for_invalid_payload(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/courses', [
            'title' => '',
            'slug' => 'invalid slug',
            'level' => 'expert',
            'access_duration_days' => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id', 'instructor_id', 'title', 'slug', 'level', 'access_duration_days']);
    }

    /** @return array<string, mixed> */
    private function validPayload(Category $category, User $instructor): array
    {
        return [
            'category_id' => $category->id,
            'instructor_id' => $instructor->id,
            'title' => 'New Course',
            'slug' => 'new-course',
            'level' => CourseLevel::Beginner->value,
        ];
    }
}
