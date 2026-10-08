<?php

namespace Tests\Feature;

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportTicketAssigneeLookupTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_lookup_lists_only_users_with_effective_support_management_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['name' => 'Academy Admin']);
        $admin->assignRole(RoleName::Admin->value);
        $support = User::factory()->create(['name' => 'Support Agent']);
        $support->assignRole(RoleName::SalesSupport->value);
        $directGrant = User::factory()->create(['name' => 'Specialist']);
        $directGrant->givePermissionTo(PermissionName::SupportTicketsManage->value);
        $student = User::factory()->create(['name' => 'Student Person']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/support-ticket-assignees')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonFragment(['id' => $support->id, 'name' => 'Support Agent'])
            ->assertJsonFragment(['id' => $directGrant->id, 'name' => 'Specialist'])
            ->assertJsonMissing(['id' => $student->id])
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonPath('meta.current_page', 1);
        $this->getJson('/api/v1/admin/support-ticket-assignees?search=Special')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $directGrant->id);
        $this->getJson("/api/v1/admin/support-ticket-assignees?id={$student->id}")->assertOk()->assertJsonCount(0, 'data');

        Sanctum::actingAs($support);
        $this->getJson('/api/v1/admin/support-ticket-assignees')->assertOk();
        Sanctum::actingAs($directGrant);
        $this->getJson('/api/v1/admin/support-ticket-assignees')->assertOk();
    }

    public function test_lookup_requires_management_permission_and_valid_filters(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        Sanctum::actingAs($student);
        $this->getJson('/api/v1/admin/support-ticket-assignees')->assertForbidden();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/admin/support-ticket-assignees?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
        $this->getJson('/api/v1/admin/support-ticket-assignees?id=abc')->assertUnprocessable()->assertJsonValidationErrors('id');
    }
}
