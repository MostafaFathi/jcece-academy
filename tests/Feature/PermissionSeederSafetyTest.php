<?php

namespace Tests\Feature;

use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionSeederSafetyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reseeding_adds_baseline_without_removing_existing_custom_grants(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $custom = Permission::findOrCreate('site.custom.grant');
        $student = Role::findByName(RoleName::Student->value);
        $student->givePermissionTo($custom);
        $count = $student->permissions()->count();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertTrue($student->fresh()->hasPermissionTo($custom));
        $this->assertTrue($student->fresh()->hasPermissionTo(PermissionName::CoursesView->value));
        $this->assertSame($count, $student->fresh()->permissions()->count());
    }

    public function test_policy_page_grants_are_limited_to_content_roles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->assertTrue(Role::findByName(RoleName::Admin->value)->hasPermissionTo(PermissionName::PolicyPagesPublish->value));
        $this->assertTrue(Role::findByName(RoleName::ContentManager->value)->hasPermissionTo(PermissionName::PolicyPagesUpdate->value));
        $this->assertFalse(Role::findByName(RoleName::ContentManager->value)->hasPermissionTo(PermissionName::PolicyPagesPublish->value));
        $this->assertFalse(Role::findByName(RoleName::SalesSupport->value)->hasPermissionTo(PermissionName::PolicyPagesView->value));
    }
}
