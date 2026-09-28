<?php

namespace Tests\Feature\Api\V1;

use App\CourseStatus;
use App\Models\Course;
use App\Models\Package;
use App\Models\PackageCourse;
use App\PackageStatus;
use App\PackageType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicPackageApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_index_returns_only_currently_published_packages(): void
    {
        $published = Package::factory()->published()->create(['slug' => 'visible-package']);
        Package::factory()->create(['slug' => 'draft-package']);
        Package::factory()->create(['slug' => 'hidden-package', 'status' => PackageStatus::Hidden]);
        Package::factory()->published()->create(['slug' => 'future-package', 'published_at' => now()->addDay()]);
        Package::factory()->published()->create(['slug' => 'deleted-package'])->delete();

        $this->getJson('/api/v1/packages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id);
    }

    public function test_show_returns_ordered_safe_course_metadata(): void
    {
        $package = Package::factory()->published()->create(['slug' => 'bim-path']);
        $firstCourse = Course::factory()->published()->create([
            'slug' => 'modeling',
            'description' => 'private-long-description',
            'promo_video_url' => 'https://secret.test/video',
        ]);
        $secondCourse = Course::factory()->published()->create(['slug' => 'documentation']);
        $deletedCourse = Course::factory()->published()->create(['slug' => 'deleted-course']);
        $draftCourse = Course::factory()->create(['slug' => 'draft-course']);
        $hiddenCourse = Course::factory()->published()->create(['slug' => 'hidden-course', 'status' => CourseStatus::Hidden]);
        $futureCourse = Course::factory()->published()->create(['slug' => 'future-course', 'published_at' => now()->addDay()]);
        PackageCourse::factory()->for($package)->for($firstCourse)->create(['sort_order' => 1]);
        PackageCourse::factory()->for($package)->for($secondCourse)->create(['sort_order' => 0, 'is_required' => false]);
        PackageCourse::factory()->for($package)->for($deletedCourse)->create(['sort_order' => 2]);
        PackageCourse::factory()->for($package)->for($draftCourse)->create(['sort_order' => 3]);
        PackageCourse::factory()->for($package)->for($hiddenCourse)->create(['sort_order' => 4]);
        PackageCourse::factory()->for($package)->for($futureCourse)->create(['sort_order' => 5]);
        $deletedCourse->delete();

        $this->getJson('/api/v1/packages/bim-path')
            ->assertOk()
            ->assertJsonPath('data.course_count', 2)
            ->assertJsonPath('data.courses.0.course.slug', 'documentation')
            ->assertJsonPath('data.courses.0.is_required', false)
            ->assertJsonPath('data.courses.1.course.slug', 'modeling')
            ->assertJsonCount(2, 'data.courses')
            ->assertJsonMissingPath('data.courses.1.course.description')
            ->assertJsonMissingPath('data.courses.1.course.promo_video_url')
            ->assertJsonMissingPath('data.courses.1.course.status')
            ->assertDontSee('private-long-description')
            ->assertDontSee('secret.test')
            ->assertDontSee('draft-course')
            ->assertDontSee('hidden-course')
            ->assertDontSee('future-course');
    }

    public function test_show_returns_not_found_for_unpublished_package(): void
    {
        $package = Package::factory()->create(['slug' => 'draft']);

        $this->getJson("/api/v1/packages/{$package->slug}")->assertNotFound();
    }

    public function test_index_filters_by_search_and_type(): void
    {
        Package::factory()->published()->learningPath()->create([
            'title' => 'BIM Career Path',
            'slug' => 'bim-career-path',
        ]);
        Package::factory()->published()->create([
            'title' => 'Management Bundle',
            'slug' => 'management-bundle',
            'type' => PackageType::Package,
        ]);

        $this->getJson('/api/v1/packages?search=BIM&type=learning_path')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'bim-career-path');
    }
}
