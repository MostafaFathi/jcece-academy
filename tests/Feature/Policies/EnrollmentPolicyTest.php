<?php

namespace Tests\Feature\Policies;

use App\Models\Enrollment;
use App\Models\User;
use App\Policies\EnrollmentPolicy;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnrollmentPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('roleAbilities')]
    public function test_role_permissions_control_enrollment_view_and_management(RoleName $role, bool $allowed): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        $policy = new EnrollmentPolicy;

        $this->assertSame($allowed, $policy->viewAny($user));
        $this->assertSame($allowed, $policy->manage($user));
    }

    public function test_enrollment_history_cannot_be_deleted_through_policy(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $enrollment = Enrollment::factory()->create();

        $allowed = (new EnrollmentPolicy)->delete($admin, $enrollment);

        $this->assertFalse($allowed);
    }

    /** @return array<string, array{RoleName, bool}> */
    public static function roleAbilities(): array
    {
        return [
            'admin' => [RoleName::Admin, true],
            'sales support' => [RoleName::SalesSupport, true],
            'content manager' => [RoleName::ContentManager, false],
            'instructor' => [RoleName::Instructor, false],
            'student' => [RoleName::Student, false],
        ];
    }
}
