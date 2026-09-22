<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\User;
use App\Policies\CoursePolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CoursePolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('abilities')]
    public function test_allows_only_the_permission_for_each_course_ability(string $ability, string $permission): void
    {
        Permission::findOrCreate($permission);
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo($permission);
        $unauthorizedUser = User::factory()->create();
        $course = Course::factory()->create();
        $policy = new CoursePolicy;

        $this->assertTrue($policy->{$ability}($authorizedUser, ...($ability === 'create' || $ability === 'viewAny' ? [] : [$course])));
        $this->assertFalse($policy->{$ability}($unauthorizedUser, ...($ability === 'create' || $ability === 'viewAny' ? [] : [$course])));
    }

    /** @return array<string, array{string, string}> */
    public static function abilities(): array
    {
        return [
            'view any' => ['viewAny', 'courses.view'],
            'view' => ['view', 'courses.view'],
            'create' => ['create', 'courses.create'],
            'update' => ['update', 'courses.update'],
            'delete' => ['delete', 'courses.delete'],
            'restore' => ['restore', 'courses.update'],
            'force delete' => ['forceDelete', 'courses.delete'],
            'publish' => ['publish', 'courses.publish'],
        ];
    }
}
