<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Course;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_role_management_requires_an_authenticated_admin_with_the_dedicated_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->getJson('/api/v1/admin/roles')->assertUnauthorized();
        foreach ([RoleName::Student, RoleName::Instructor, RoleName::ContentManager, RoleName::SalesSupport] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);
            Sanctum::actingAs($user);
            $this->getJson('/api/v1/admin/roles')->assertForbidden();
            $this->putJson('/api/v1/admin/roles/student/permissions', [])->assertForbidden();
        }

        $admin = $this->admin();
        $admin->revokePermissionTo(PermissionName::RolesManage->value);
        Role::findByName(RoleName::Admin->value, 'web')->revokePermissionTo(PermissionName::RolesManage->value);
        Sanctum::actingAs($admin->fresh());
        $this->getJson('/api/v1/admin/roles')->assertForbidden();
    }

    public function test_authorized_admin_can_list_only_registry_permissions_and_system_roles(): void
    {
        $this->admin();
        $response = $this->getJson('/api/v1/admin/roles')->assertOk()
            ->assertJsonCount(5, 'roles')
            ->assertJsonCount(count(PermissionName::cases()), 'permissions')
            ->assertJsonPath('permissions.0.key', PermissionName::CoursesView->value)
            ->assertJsonMissingPath('roles.0.email');
        $this->assertSame(5, count($response->json('roles')));
        $management = collect($response->json('permissions'))->firstWhere('key', PermissionName::RolesManage->value);
        $this->assertSame('access', $management['group']);
        $this->assertTrue($management['sensitive']);
        $this->getJson('/api/v1/admin/roles/unknown')->assertNotFound();
    }

    public function test_update_adds_and_removes_permissions_and_effect_takes_place_on_new_request(): void
    {
        $admin = $this->admin();
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        $this->assertFalse($student->can(PermissionName::CoursesCreate->value));
        $snapshot = $this->getJson('/api/v1/admin/roles/student')->assertOk()->json();
        $permissions = [...$snapshot['permissions'], PermissionName::CoursesCreate->value];

        $this->putJson('/api/v1/admin/roles/student/permissions', [
            'permissions' => $permissions,
            'version' => $snapshot['version'],
        ])->assertOk()->assertJsonPath('role', RoleName::Student->value);
        $this->assertTrue($student->fresh()->can(PermissionName::CoursesCreate->value));

        Sanctum::actingAs($student);
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonFragment([PermissionName::CoursesCreate->value]);
        Sanctum::actingAs($admin);
        $updated = $this->getJson('/api/v1/admin/roles/student')->json();
        $this->putJson('/api/v1/admin/roles/student/permissions', [
            'permissions' => $snapshot['permissions'],
            'version' => $updated['version'],
        ])->assertOk();
        $this->assertFalse($student->fresh()->can(PermissionName::CoursesCreate->value));
        $this->assertDatabaseHas('audit_events', ['event_type' => 'role.permission_removed', 'subject_type' => 'Role']);
    }

    public function test_unknown_permission_unknown_role_and_non_admin_management_grant_are_rejected(): void
    {
        $this->admin();
        $snapshot = $this->getJson('/api/v1/admin/roles/student')->json();
        $this->putJson('/api/v1/admin/roles/student/permissions', [
            'permissions' => ['imaginary.manage'],
            'version' => $snapshot['version'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
        $this->putJson('/api/v1/admin/roles/student/permissions', [
            'permissions' => [PermissionName::RolesManage->value],
            'version' => $snapshot['version'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->putJson('/api/v1/admin/roles/unknown/permissions', [
            'permissions' => [],
            'version' => $snapshot['version'],
        ])->assertNotFound();
    }

    public function test_stale_update_is_rejected_without_overwriting_first_admin(): void
    {
        $this->admin();
        $snapshot = $this->getJson('/api/v1/admin/roles/student')->json();
        $first = [...$snapshot['permissions'], PermissionName::CoursesCreate->value];
        $this->putJson('/api/v1/admin/roles/student/permissions', ['permissions' => $first, 'version' => $snapshot['version']])->assertOk();
        $this->putJson('/api/v1/admin/roles/student/permissions', ['permissions' => $snapshot['permissions'], 'version' => $snapshot['version']])->assertStatus(409);
        $this->assertTrue(Role::findByName(RoleName::Student->value, 'web')->hasPermissionTo(PermissionName::CoursesCreate->value));
    }

    public function test_last_active_admin_manager_cannot_be_locked_out(): void
    {
        $admin = $this->admin();
        $snapshot = $this->getJson('/api/v1/admin/roles/admin')->json();
        $withoutManagement = array_values(array_diff($snapshot['permissions'], [PermissionName::RolesManage->value]));
        $this->putJson('/api/v1/admin/roles/admin/permissions', [
            'permissions' => $withoutManagement,
            'version' => $snapshot['version'],
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->assertTrue($admin->fresh()->can(PermissionName::RolesManage->value));
    }

    public function test_direct_grant_can_preserve_recovery_but_last_manager_user_cannot_be_demoted(): void
    {
        $admin = $this->admin();
        $recovery = User::factory()->create();
        $recovery->assignRole(RoleName::Admin->value);
        $recovery->assignRole(RoleName::Instructor->value);
        $recovery->givePermissionTo(PermissionName::RolesManage->value);
        $snapshot = $this->getJson('/api/v1/admin/roles/admin')->json();
        $this->putJson('/api/v1/admin/roles/admin/permissions', [
            'permissions' => array_values(array_diff($snapshot['permissions'], [PermissionName::RolesManage->value])),
            'version' => $snapshot['version'],
        ])->assertOk();
        $this->assertFalse($admin->fresh()->can(PermissionName::RolesManage->value));
        $this->patchJson("/api/v1/admin/instructors/{$recovery->id}", ['status' => 'blocked'])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions');
        $this->patchJson("/api/v1/admin/users/{$recovery->id}", ['roles' => [RoleName::Instructor->value, RoleName::Student->value]])
            ->assertForbidden();
        $this->assertTrue($recovery->fresh()->hasRole(RoleName::Admin->value));
    }

    public function test_role_permission_changes_create_safe_audit_events_and_preserve_unmanaged_grants(): void
    {
        $admin = $this->admin();
        $role = Role::findByName(RoleName::Student->value, 'web');
        $role->givePermissionTo(Permission::findOrCreate('legacy.custom', 'web'));
        $snapshot = $this->getJson('/api/v1/admin/roles/student')->json();
        $this->assertSame(1, $snapshot['unmanaged_count']);
        $this->putJson('/api/v1/admin/roles/student/permissions', [
            'permissions' => [...$snapshot['permissions'], PermissionName::CoursesCreate->value],
            'version' => $snapshot['version'],
        ])->assertOk();
        $this->assertTrue($role->fresh()->hasPermissionTo('legacy.custom'));
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'event_type' => 'role.permission_added', 'subject_type' => 'Role', 'subject_id' => $role->id]);
        $event = AuditEvent::query()->where('event_type', 'role.permissions_changed')->firstOrFail();
        $this->assertSame(RoleName::Student->value, $event->metadata['role']);
        $this->assertSame(PermissionName::CoursesCreate->value, $event->metadata['added_permissions']);
    }

    public function test_instructor_permission_does_not_override_course_ownership_policy(): void
    {
        $this->admin();
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::Instructor->value);
        $other = User::factory()->create();
        $other->assignRole(RoleName::Instructor->value);
        $course = Course::factory()->create(['instructor_id' => $owner->id]);
        $snapshot = $this->getJson('/api/v1/admin/roles/instructor')->json();
        $this->putJson('/api/v1/admin/roles/instructor/permissions', [
            'permissions' => [...$snapshot['permissions'], PermissionName::CurriculumUpdate->value],
            'version' => $snapshot['version'],
        ])->assertOk();

        $this->assertTrue($other->fresh()->can(PermissionName::CurriculumUpdate->value));
        $this->assertFalse($other->fresh()->can('update', $course));
    }

    public function test_admin_user_detail_separates_direct_and_inherited_grants_and_role_changes_are_audited(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $user->assignRole(RoleName::Student->value);
        $user->givePermissionTo(PermissionName::SupportTicketsView->value);

        $this->getJson("/api/v1/admin/users/{$user->id}")->assertOk()
            ->assertJsonFragment(['direct' => [PermissionName::SupportTicketsView->value]])
            ->assertJsonPath('data.permission_sources.inherited.0', PermissionName::CategoriesView->value);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        Sanctum::actingAs($support);
        $this->getJson("/api/v1/admin/users/{$user->id}")->assertOk()->assertJsonMissingPath('data.permission_sources');
        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/users/{$user->id}", ['roles' => [RoleName::SalesSupport->value]])->assertOk();
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'event_type' => 'user.role_added', 'subject_id' => $user->id]);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $admin->id, 'event_type' => 'user.role_removed', 'subject_id' => $user->id]);
    }

    public function test_non_admin_user_manager_cannot_assign_admin_role_or_modify_admin_account(): void
    {
        $admin = $this->admin();
        $admin->assignRole(RoleName::Instructor->value);
        $support = User::factory()->create();
        $support->assignRole(RoleName::SalesSupport->value);
        $support->givePermissionTo([PermissionName::UsersManage->value, PermissionName::InstructorsManage->value]);
        Sanctum::actingAs($support);

        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Admin', 'email' => 'new-admin@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'roles' => [RoleName::Admin->value],
        ])->assertForbidden();
        $this->postJson('/api/v1/admin/users', [
            'name' => 'New Learner', 'email' => 'new-learner@example.test',
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'roles' => [RoleName::Student->value],
        ])->assertCreated()->assertJsonPath('data.roles.0', RoleName::Student->value);
        $this->patchJson("/api/v1/admin/users/{$support->id}", ['roles' => [RoleName::Admin->value]])->assertForbidden();
        $this->patchJson("/api/v1/admin/users/{$admin->id}", ['password' => 'AnotherPass123', 'password_confirmation' => 'AnotherPass123'])->assertForbidden();
        $this->patchJson("/api/v1/admin/instructors/{$admin->id}", ['password' => 'AnotherPass123', 'password_confirmation' => 'AnotherPass123'])->assertForbidden();
        $this->assertFalse($support->fresh()->hasRole(RoleName::Admin->value));
    }

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        return $admin;
    }
}
