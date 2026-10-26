<?php

namespace Tests\Feature;

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SuperAdminSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_verified_active_admin_with_all_registered_permissions_and_hashed_password(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $owner = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $this->assertTrue($owner->is_super_admin);
        $this->assertTrue($owner->hasRole(RoleName::Admin->value));
        $this->assertTrue($owner->hasDirectPermission(PermissionName::RolesManage->value));
        $this->assertTrue($owner->hasDirectPermission(PermissionName::MessagingGroupsManage->value));
        $this->assertSame(Permission::query()->where('guard_name', 'web')->count(), $owner->permissions()->count());
        $this->assertTrue(Hash::check('password', $owner->password));
        $this->assertNotNull($owner->email_verified_at);
        $this->assertSame('active', $owner->status->value);
    }

    public function test_reseeding_keeps_existing_password_and_does_not_create_another_owner(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $owner = User::query()->where('is_super_admin', true)->firstOrFail();
        $owner->update(['password' => 'ChangedPassword123']);

        $this->seed(SuperAdminSeeder::class);

        $this->assertSame(1, User::query()->where('is_super_admin', true)->count());
        $this->assertTrue(Hash::check('ChangedPassword123', $owner->fresh()->password));
    }

    public function test_rejects_existing_email_owned_by_a_regular_account(): void
    {
        $existing = User::factory()->create(['email' => 'superadmin@email.com']);

        try {
            $this->seed(SuperAdminSeeder::class);
            $this->fail('Seeder should reject an existing account.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The configured super-admin email already belongs to another account.', $exception->getMessage());
        }

        $this->assertFalse($existing->fresh()->is_super_admin);
        $this->assertSame(0, User::query()->where('is_super_admin', true)->count());
    }

    public function test_other_admin_cannot_list_view_or_update_super_admin(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $owner = User::query()->where('is_super_admin', true)->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/users?search=superadmin%40email.com')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/admin/users/{$owner->id}")->assertNotFound();
        $this->patchJson("/api/v1/admin/users/{$owner->id}", ['name' => 'Changed'])->assertNotFound();

        $this->assertSame('superadmin@email.com', $owner->fresh()->email);
        $this->assertNotSame('Changed', $owner->fresh()->name);
    }

    public function test_super_admin_can_view_self_but_cannot_remove_own_admin_role_or_change_seed_email(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $owner = User::query()->where('is_super_admin', true)->firstOrFail();
        Sanctum::actingAs($owner);

        $this->getJson("/api/v1/admin/users/{$owner->id}")
            ->assertOk()->assertJsonPath('data.email', 'superadmin@email.com')
            ->assertJsonMissingPath('data.is_super_admin');
        $this->patchJson("/api/v1/admin/users/{$owner->id}", ['email' => 'other@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson("/api/v1/admin/users/{$owner->id}", ['roles' => [RoleName::Student->value]])
            ->assertUnprocessable()->assertJsonValidationErrors('roles');

        $this->assertSame('superadmin@email.com', $owner->fresh()->email);
        $this->assertTrue($owner->fresh()->hasRole(RoleName::Admin->value));
    }

    public function test_other_admin_cannot_find_super_admin_in_support_assignees(): void
    {
        $this->seed(SuperAdminSeeder::class);
        $owner = User::query()->where('is_super_admin', true)->firstOrFail();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        $this->getJson("/api/v1/admin/support-ticket-assignees?id={$owner->id}")
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
