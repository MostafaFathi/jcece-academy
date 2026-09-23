<?php

namespace Tests\Feature\Policies;

use App\Models\Package;
use App\Models\User;
use App\Policies\PackagePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PackagePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('abilities')]
    public function test_allows_only_the_permission_for_each_package_ability(string $ability, string $permission): void
    {
        Permission::findOrCreate($permission);
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo($permission);
        $unauthorizedUser = User::factory()->create();
        $package = Package::factory()->create();
        $policy = new PackagePolicy;

        $arguments = in_array($ability, ['create', 'viewAny'], true) ? [] : [$package];

        $this->assertTrue($policy->{$ability}($authorizedUser, ...$arguments));
        $this->assertFalse($policy->{$ability}($unauthorizedUser, ...$arguments));
    }

    /** @return array<string, array{string, string}> */
    public static function abilities(): array
    {
        return [
            'view any' => ['viewAny', 'packages.view'],
            'view' => ['view', 'packages.view'],
            'create' => ['create', 'packages.create'],
            'update' => ['update', 'packages.update'],
            'delete' => ['delete', 'packages.delete'],
            'restore' => ['restore', 'packages.update'],
            'force delete' => ['forceDelete', 'packages.delete'],
            'publish' => ['publish', 'packages.publish'],
        ];
    }
}
