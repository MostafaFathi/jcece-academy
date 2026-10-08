<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemPackageCourse;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\Quiz;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\CourseProgressService;
use App\Services\OrderAccessProvisioningService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SequentialPackageAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_snapshot_order_threshold_and_expiration_govern_sequential_access(): void
    {
        $this->travelTo('2026-10-04 12:00:00');
        $student = User::factory()->create();
        Role::findOrCreate('student', 'web');
        $student->assignRole('student');
        $package = Package::factory()->learningPath()->create(['sequential_completion_percentage' => '75.00']);
        $courses = Course::factory()->count(3)->create();
        $order = Order::factory()->paid()->for($student)->create();
        $item = OrderItem::factory()->for($order)->forPackage($package)->create(['access_duration_days' => 30, 'sequential_completion_percentage' => '75.00']);
        foreach ($courses as $index => $course) {
            OrderItemPackageCourse::factory()->for($item)->for($course)->create(['sort_order' => $index + 1]);
        }
        $firstLessons = Lesson::factory()->published()->count(4)->for(CourseSection::factory()->for($courses[0]), 'section')->create();
        $secondLessons = Lesson::factory()->published()->count(4)->for(CourseSection::factory()->for($courses[1]), 'section')->create();

        $provisioning = app(OrderAccessProvisioningService::class);
        $this->assertSame(3, $provisioning->provision($order)['grants_created']);
        $this->assertSame(0, $provisioning->provision($order)['grants_created']);
        $access = app(CourseAccessService::class);
        $this->assertTrue($access->hasAccess($student, $courses[0]));
        $this->assertFalse($access->hasAccess($student, $courses[1]));
        $this->assertFalse($access->hasAccess($student, $courses[2]));
        $this->assertSame('locked', $access->accessMetadata($this->enrollment($student, $courses[1]))['access_state']);
        $this->actingAs($student)->getJson("/api/v1/me/courses/{$courses[1]->slug}/learn")->assertForbidden();
        $lockedResource = LessonResource::factory()->for($secondLessons[0])->create();
        $this->actingAs($student)->get("/api/v1/me/courses/{$courses[1]->slug}/lessons/{$secondLessons[0]->id}/resources/{$lockedResource->id}/download")->assertForbidden();
        $quiz = Quiz::factory()->published()->for($courses[1])->create();
        $assignment = Assignment::factory()->published()->for($courses[1])->create();
        $this->actingAs($student)->getJson("/api/v1/me/quizzes/{$quiz->id}")->assertForbidden();
        $this->actingAs($student)->getJson("/api/v1/me/assignments/{$assignment->id}")->assertForbidden();
        $secondLessons[0]->update(['type' => 'video', 'protected_video_asset_key' => 'private/second-video']);
        $this->actingAs($student)->postJson("/api/v1/me/courses/{$courses[1]->slug}/lessons/{$secondLessons[0]->id}/protected-playback")->assertForbidden();

        $progress = app(CourseProgressService::class);
        $firstEnrollment = $this->enrollment($student, $courses[0]);
        $progress->completeLesson($firstEnrollment, $firstLessons[0]);
        $progress->completeLesson($firstEnrollment, $firstLessons[1]);
        $this->assertFalse($access->hasAccess($student, $courses[1]));
        $progress->completeLesson($firstEnrollment, $firstLessons[2]);
        $this->assertTrue($access->hasAccess($student, $courses[1]));
        $this->assertFalse($access->hasAccess($student, $courses[2]));
        $progress->completeLesson($firstEnrollment, $firstLessons[3]);
        $this->assertTrue($access->hasAccess($student, $courses[1]));

        PackageCourse::factory()->for($package)->for($courses[2])->create(['sort_order' => 0]);
        PackageCourse::factory()->for($package)->for($courses[0])->create(['sort_order' => 2]);
        $this->assertSame(2, $access->accessMetadata($this->enrollment($student, $courses[1]))['sequential']['course_order']);

        foreach ($secondLessons->take(3) as $lesson) {
            $progress->completeLesson($this->enrollment($student, $courses[1]), $lesson);
        }
        $this->assertTrue($access->hasAccess($student, $courses[2]));
        $package->update(['sequential_completion_percentage' => '100.00']);
        $this->assertTrue($access->hasAccess($student, $courses[2]));
        $this->travel(31)->days();
        $this->assertFalse($access->hasAccess($student, $courses[2]));
        $this->assertSame('expired', $access->accessMetadata($this->enrollment($student, $courses[2]))['access_state']);
    }

    public function test_legacy_package_purchase_remains_unrestricted(): void
    {
        $student = User::factory()->create();
        $package = Package::factory()->learningPath()->create();
        $courses = Course::factory()->count(2)->create();
        $order = Order::factory()->paid()->for($student)->create();
        $item = OrderItem::factory()->for($order)->forPackage($package)->create(['sequential_completion_percentage' => null]);
        foreach ($courses as $index => $course) {
            OrderItemPackageCourse::factory()->for($item)->for($course)->create(['sort_order' => $index]);
        }

        app(OrderAccessProvisioningService::class)->provision($order);

        $this->assertTrue(app(CourseAccessService::class)->hasAccess($student, $courses[1]));
    }

    private function enrollment(User $user, Course $course): Enrollment
    {
        return Enrollment::query()->whereBelongsTo($user)->whereBelongsTo($course)->sole();
    }
}
