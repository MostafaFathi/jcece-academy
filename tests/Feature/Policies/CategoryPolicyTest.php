<?php

namespace Tests\Feature\Policies;

use App\Models\Category;
use App\Models\User;
use App\Policies\CategoryPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CategoryPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('abilities')]
    public function test_allows_only_the_permission_for_each_category_ability(string $ability, string $permission): void
    {
        Permission::findOrCreate($permission);
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo($permission);
        $unauthorizedUser = User::factory()->create();
        $category = Category::factory()->create();
        $policy = new CategoryPolicy;

        $this->assertTrue($policy->{$ability}($authorizedUser, ...($ability === 'create' || $ability === 'viewAny' ? [] : [$category])));
        $this->assertFalse($policy->{$ability}($unauthorizedUser, ...($ability === 'create' || $ability === 'viewAny' ? [] : [$category])));
    }

    /** @return array<string, array{string, string}> */
    public static function abilities(): array
    {
        return [
            'view any' => ['viewAny', 'categories.view'],
            'view' => ['view', 'categories.view'],
            'create' => ['create', 'categories.create'],
            'update' => ['update', 'categories.update'],
            'delete' => ['delete', 'categories.delete'],
            'restore' => ['restore', 'categories.update'],
            'force delete' => ['forceDelete', 'categories.delete'],
        ];
    }
}
