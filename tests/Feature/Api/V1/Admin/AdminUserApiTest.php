<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminUserApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_401_without_authentication_for_user_management_routes(): void
    {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();
        $this->postJson('/api/v1/admin/users', [])->assertUnauthorized();
        $this->getJson('/api/v1/admin/users/1')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/users/1', [])->assertUnauthorized();
    }

    public function test_returns_403_to_content_manager_for_user_listing_and_creation(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole(RoleName::ContentManager->value);
        Sanctum::actingAs($manager);

        $this->getJson('/api/v1/admin/users')->assertForbidden();
        $this->postJson('/api/v1/admin/users', $this->newUserPayload())->assertForbidden();
    }

    public function test_admin_lists_filtered_users_with_pagination_and_safe_fields(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->create(['name' => 'Unique Learner', 'status' => 'active']);
        $student->assignRole(RoleName::Student->value);
        User::factory()->create(['name' => 'Other Person', 'status' => 'blocked']);

        $this->getJson('/api/v1/admin/users?search=Unique&role=student&status=active&per_page=1')
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $student->id)
            ->assertJsonPath('data.0.roles.0', RoleName::Student->value)
            ->assertJsonMissingPath('data.0.password')->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonMissingPath('data.0.permissions')->assertJsonMissingPath('data.0.last_login_at');

        $this->getJson("/api/v1/admin/users/{$student->id}")
            ->assertOk()->assertJsonPath('data.email', $student->email)->assertJsonMissingPath('data.password');
    }

    public function test_admin_creates_user_with_hashed_password_and_validated_role(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/users', $this->newUserPayload());

        $response->assertCreated()->assertJsonPath('data.email', 'new.student@example.test')
            ->assertJsonPath('data.roles.0', RoleName::Student->value)->assertJsonMissingPath('data.password');
        $created = User::where('email', 'new.student@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('StrongPass123', $created->password));
    }

    public function test_returns_422_for_duplicate_email_weak_password_and_instructor_role_on_user_creation(): void
    {
        $this->actingAsAdmin();
        User::factory()->create(['email' => 'new.student@example.test']);

        $this->postJson('/api/v1/admin/users', [
            ...$this->newUserPayload(), 'password' => 'short', 'password_confirmation' => 'short', 'roles' => [RoleName::Instructor->value],
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'roles.0']);
    }

    public function test_admin_updates_roles_and_password_only_when_explicitly_supplied(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $originalHash = $student->password;

        $this->patchJson("/api/v1/admin/users/{$student->id}", ['name' => 'Updated Learner', 'password' => ''])
            ->assertOk()->assertJsonPath('data.name', 'Updated Learner');
        $this->assertSame($originalHash, $student->refresh()->password);

        $this->patchJson("/api/v1/admin/users/{$student->id}", [
            'password' => 'AnotherPass123', 'password_confirmation' => 'AnotherPass123', 'roles' => [RoleName::SalesSupport->value],
        ])->assertOk()->assertJsonPath('data.roles.0', RoleName::SalesSupport->value);
        $this->assertTrue(Hash::check('AnotherPass123', $student->refresh()->password));
    }

    public function test_returns_422_when_admin_removes_own_admin_role_or_blocks_self(): void
    {
        $admin = $this->actingAsAdmin();

        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['roles' => [RoleName::Student->value]])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['status' => 'blocked'])
            ->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertTrue($admin->fresh()->hasRole(RoleName::Admin->value));
        $this->assertSame('active', $admin->fresh()->status->value);
    }

    public function test_returns_422_when_user_update_would_add_or_remove_instructor_membership(): void
    {
        $this->actingAsAdmin();
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);

        $this->patchJson("/api/v1/admin/users/{$student->id}", ['roles' => [RoleName::Instructor->value]])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');
        $this->patchJson("/api/v1/admin/users/{$instructor->id}", ['roles' => [RoleName::Student->value]])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');
    }

    public function test_sales_support_can_read_and_edit_basic_fields_but_cannot_escalate_privileges(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($support);

        $this->getJson('/api/v1/admin/users')->assertOk();
        $this->postJson('/api/v1/admin/users', $this->newUserPayload())->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$student->id}", ['name' => 'Changed by support'])
            ->assertOk()->assertJsonPath('data.name', 'Changed by support');
        $this->patchJson("/api/v1/admin/users/{$student->id}", ['roles' => [RoleName::Admin->value]])->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$student->id}", ['password' => 'AnotherPass123', 'password_confirmation' => 'AnotherPass123'])->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$student->id}", ['status' => 'blocked'])->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['name' => 'Locked out?'])->assertForbidden();
        $this->assertFalse($student->fresh()->hasRole(RoleName::Admin->value));
        $this->assertSame('active', $student->fresh()->status->value);
    }

    /** @return array<string, mixed> */
    private function newUserPayload(): array
    {
        return [
            'name' => 'New Student', 'email' => 'new.student@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'roles' => [RoleName::Student->value],
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
