<?php

namespace Tests\Feature;

use App\Models\CourseReview;
use App\Models\User;
use App\Policies\CourseReviewPolicy;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseReviewPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_owner_can_view_and_update_but_cannot_moderate_review(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $owner = User::factory()->create();
        $owner->assignRole(RoleName::Student->value);
        $review = CourseReview::factory()->for($owner)->create();
        $policy = app(CourseReviewPolicy::class);

        $this->assertTrue($policy->view($owner, $review));
        $this->assertTrue($policy->update($owner, $review));
        $this->assertFalse($policy->moderate($owner, $review));
        $this->assertFalse($policy->delete($owner, $review));
    }

    #[DataProvider('reviewManagementRoles')]
    public function test_admin_and_content_manager_can_view_and_moderate_reviews(RoleName $role): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $manager = User::factory()->create();
        $manager->assignRole($role->value);
        $review = CourseReview::factory()->create();
        $policy = app(CourseReviewPolicy::class);

        $this->assertTrue($policy->viewAny($manager));
        $this->assertTrue($policy->view($manager, $review));
        $this->assertTrue($policy->moderate($manager, $review));
        $this->assertFalse($policy->update($manager, $review));
    }

    public function test_instructor_has_no_global_review_visibility_or_moderation_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $review = CourseReview::factory()->create();
        $policy = app(CourseReviewPolicy::class);

        $this->assertFalse($policy->viewAny($instructor));
        $this->assertFalse($policy->view($instructor, $review));
        $this->assertFalse($policy->moderate($instructor, $review));
    }

    /** @return array<string, array{RoleName}> */
    public static function reviewManagementRoles(): array
    {
        return [
            'admin' => [RoleName::Admin],
            'content manager' => [RoleName::ContentManager],
        ];
    }
}
