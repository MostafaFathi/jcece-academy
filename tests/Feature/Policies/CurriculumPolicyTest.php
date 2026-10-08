<?php

namespace Tests\Feature\Policies;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use App\Policies\CourseSectionPolicy;
use App\Policies\LessonPolicy;
use App\Policies\LessonResourcePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CurriculumPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  class-string  $policyClass
     * @param  class-string<Model>  $targetClass
     */
    #[DataProvider('abilities')]
    public function test_curriculum_abilities_require_the_matching_permission(string $policyClass, string $ability, string $permission, string $targetClass): void
    {
        foreach (array_unique(['courses.view', 'curriculum.view', $permission]) as $key) {
            Permission::findOrCreate($key);
        }
        $authorizedUser = User::factory()->create();
        $authorizedUser->givePermissionTo(array_unique(['courses.view', 'curriculum.view', $permission]));
        $unauthorizedUser = User::factory()->create();
        $unauthorizedUser->givePermissionTo($permission === 'curriculum.view' ? ['courses.view'] : ['courses.view', 'curriculum.view']);
        $target = $this->createTarget($targetClass);
        $policy = new $policyClass;

        $this->assertTrue($policy->{$ability}($authorizedUser, $target));
        $this->assertFalse($policy->{$ability}($unauthorizedUser, $target));
    }

    /** @return array<string, array{class-string, string, string, class-string<Model>}> */
    public static function abilities(): array
    {
        return [
            'section view' => [CourseSectionPolicy::class, 'view', 'curriculum.view', CourseSection::class],
            'section create' => [CourseSectionPolicy::class, 'create', 'curriculum.create', Course::class],
            'section update' => [CourseSectionPolicy::class, 'update', 'curriculum.update', CourseSection::class],
            'section delete' => [CourseSectionPolicy::class, 'delete', 'curriculum.delete', CourseSection::class],
            'section reorder' => [CourseSectionPolicy::class, 'reorder', 'curriculum.update', Course::class],
            'lesson view' => [LessonPolicy::class, 'view', 'curriculum.view', Lesson::class],
            'lesson create' => [LessonPolicy::class, 'create', 'curriculum.create', CourseSection::class],
            'lesson update' => [LessonPolicy::class, 'update', 'curriculum.update', Lesson::class],
            'lesson delete' => [LessonPolicy::class, 'delete', 'curriculum.delete', Lesson::class],
            'lesson reorder' => [LessonPolicy::class, 'reorder', 'curriculum.update', CourseSection::class],
            'resource view' => [LessonResourcePolicy::class, 'view', 'curriculum.view', LessonResource::class],
            'resource create' => [LessonResourcePolicy::class, 'create', 'curriculum.create', Lesson::class],
            'resource update' => [LessonResourcePolicy::class, 'update', 'curriculum.update', LessonResource::class],
            'resource delete' => [LessonResourcePolicy::class, 'delete', 'curriculum.delete', LessonResource::class],
            'resource reorder' => [LessonResourcePolicy::class, 'reorder', 'curriculum.update', Lesson::class],
        ];
    }

    /** @param class-string<Model> $targetClass */
    private function createTarget(string $targetClass): Model
    {
        return $targetClass::factory()->create();
    }
}
