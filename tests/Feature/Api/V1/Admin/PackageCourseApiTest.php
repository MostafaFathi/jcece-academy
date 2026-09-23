<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PackageCourseApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_attaches_course_with_next_sort_order_without_granting_access(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        PackageCourse::factory()->for($package)->create(['sort_order' => 3]);
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses", [
            'course_id' => $course->id,
            'is_required' => false,
        ])->assertCreated()
            ->assertJsonPath('data.course_id', $course->id)
            ->assertJsonPath('data.sort_order', 4)
            ->assertJsonPath('data.is_required', false);

        $this->assertDatabaseHas('package_courses', [
            'package_id' => $package->id,
            'course_id' => $course->id,
            'sort_order' => 4,
        ]);
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_duplicate_or_soft_deleted_course_cannot_be_attached(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        $course = Course::factory()->create();
        PackageCourse::factory()->for($package)->for($course)->create();

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses", ['course_id' => $course->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');

        $deletedCourse = Course::factory()->create();
        $deletedCourse->delete();
        $this->postJson("/api/v1/admin/packages/{$package->id}/courses", ['course_id' => $deletedCourse->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course_id');
    }

    public function test_admin_updates_requirement_and_detaches_membership(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        $membership = PackageCourse::factory()->for($package)->create(['is_required' => true]);

        $this->patchJson("/api/v1/admin/packages/{$package->id}/courses/{$membership->id}", [
            'is_required' => false,
        ])->assertOk()->assertJsonPath('data.is_required', false);

        $this->deleteJson("/api/v1/admin/packages/{$package->id}/courses/{$membership->id}")
            ->assertNoContent();
        $this->assertDatabaseMissing('package_courses', ['id' => $membership->id]);
    }

    public function test_reorder_requires_exact_owned_membership_set_and_is_deterministic(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        $first = PackageCourse::factory()->for($package)->create(['sort_order' => 0]);
        $second = PackageCourse::factory()->for($package)->create(['sort_order' => 1]);
        $third = PackageCourse::factory()->for($package)->create(['sort_order' => 2]);

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses/reorder", [
            'ids' => [$third->id, $first->id, $second->id],
        ])->assertOk()
            ->assertJsonPath('data.0.id', $third->id)
            ->assertJsonPath('data.0.sort_order', 0)
            ->assertJsonPath('data.2.id', $second->id);

        $this->assertDatabaseHas('package_courses', ['id' => $first->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('package_courses', ['id' => $second->id, 'sort_order' => 2]);
        $this->assertDatabaseHas('package_courses', ['id' => $third->id, 'sort_order' => 0]);

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses/reorder", [
            'ids' => [$first->id, $second->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_reorder_rejects_membership_owned_by_another_package(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        $owned = PackageCourse::factory()->for($package)->create();
        $foreign = PackageCourse::factory()->create();

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses/reorder", [
            'ids' => [$owned->id, $foreign->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_scoped_binding_hides_membership_from_another_package(): void
    {
        $this->actingAsAdmin();
        $package = Package::factory()->create();
        $foreignMembership = PackageCourse::factory()->create();

        $this->patchJson("/api/v1/admin/packages/{$package->id}/courses/{$foreignMembership->id}", [
            'is_required' => false,
        ])->assertNotFound();
    }

    public function test_student_cannot_modify_package_memberships(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        Sanctum::actingAs($student);
        $package = Package::factory()->create();
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/packages/{$package->id}/courses", ['course_id' => $course->id])
            ->assertForbidden();
    }

    private function actingAsAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);

        return $admin;
    }
}
