<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FrontendIntegrationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_csrf_cookie_login_current_user_and_logout_use_the_session_guard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create(['password' => 'correct-password']);
        $user->assignRole(RoleName::SalesSupport->value);

        $this->get('/sanctum/csrf-cookie')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.roles.0', RoleName::SalesSupport->value)
            ->assertJsonFragment(['support_tickets.manage']);
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonPath('data.email', $user->email);
        $this->assertNotNull($user->fresh()->last_login_at);

        $this->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->getJson('/api/v1/auth/user')->assertUnauthorized();
    }

    public function test_current_user_includes_safe_instructor_profile_and_omits_security_attributes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(RoleName::Instructor->value);
        InstructorProfile::factory()->for($user)->create(['job_title' => 'Trainer']);
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/auth/user')->assertOk()
            ->assertJsonPath('data.instructor_profile.job_title', 'Trainer')
            ->assertJsonPath('data.roles.0', RoleName::Instructor->value);
        $response->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
    }

    public function test_invalid_or_inactive_credentials_return_standard_validation_json(): void
    {
        $user = User::factory()->create(['password' => 'correct-password', 'status' => UserStatus::Blocked]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['email']]);
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_api_errors_are_json_without_accept_header(): void
    {
        $this->get('/api/v1/auth/user')->assertUnauthorized()->assertHeader('content-type', 'application/json');
        $this->get('/api/v1/courses/not-a-real-course')->assertNotFound()->assertHeader('content-type', 'application/json');
    }

    public function test_public_course_pagination_has_stable_laravel_metadata(): void
    {
        Course::factory()->count(16)->published()->create();

        $this->getJson('/api/v1/courses?per_page=10')->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next'], 'meta']);
    }

    public function test_student_session_cannot_access_representative_admin_endpoint(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/orders')->assertForbidden();
    }
}
