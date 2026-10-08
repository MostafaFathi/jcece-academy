<?php

namespace Tests\Feature;

use App\CourseStatus;
use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseSection;
use App\Models\InstructorProfile;
use App\Models\Lesson;
use App\Models\Package;
use App\Models\PackageCourse;
use App\Models\SiteFaq;
use App\Models\SitePage;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicSitePhase15FTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalog_filters_training_type_and_authoritative_active_price(): void
    {
        $cheap = Course::factory()->published()->create(['slug' => 'cheap-live', 'training_type' => 'live', 'price' => '100.00', 'promotional_price' => '0.00', 'discount_starts_at' => now()->subDay(), 'discount_ends_at' => now()->addDay()]);
        Course::factory()->published()->create(['slug' => 'paid-recorded', 'training_type' => 'recorded', 'price' => '50.00']);

        $this->getJson('/api/v1/courses?training_type=live&price_type=free&sort=price_asc')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $cheap->id)
            ->assertJsonPath('data.0.price', '0.00');
        $this->getJson('/api/v1/courses?training_type=invalid')->assertUnprocessable();
    }

    public function test_public_detail_contains_only_active_faq_and_derived_published_related_courses(): void
    {
        $course = Course::factory()->published()->create();
        $related = Course::factory()->published()->create(['category_id' => $course->category_id]);
        $draft = Course::factory()->create(['category_id' => $course->category_id, 'status' => CourseStatus::Draft]);
        CourseFaq::factory()->for($course)->create(['question_en' => 'Visible?', 'is_active' => true]);
        CourseFaq::factory()->for($course)->create(['question_en' => 'Hidden?', 'is_active' => false]);

        $response = $this->getJson("/api/v1/courses/{$course->slug}")->assertOk()
            ->assertJsonCount(1, 'data.faqs')->assertJsonPath('data.faqs.0.question_en', 'Visible?');
        $this->assertContains($related->id, array_column($response->json('data.related_courses'), 'id'));
        $this->assertNotContains($draft->id, array_column($response->json('data.related_courses'), 'id'));
    }

    public function test_public_curriculum_never_exposes_unmarked_paid_lesson_content(): void
    {
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->for($course)->create(['is_active' => true]);
        Lesson::factory()->published()->for($section, 'section')->create(['is_preview' => false, 'content' => 'PRIVATE-MATERIAL', 'video_url' => 'https://private.example/video']);

        $response = $this->getJson("/api/v1/courses/{$course->slug}")->assertOk();
        $this->assertStringNotContainsString('PRIVATE-MATERIAL', $response->getContent());
        $this->assertStringNotContainsString('private.example', $response->getContent());
    }

    public function test_public_instructor_response_excludes_private_user_fields(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create(['email' => 'private-instructor@example.test', 'phone' => '0599999999']);
        $instructor->assignRole(RoleName::Instructor->value);
        InstructorProfile::factory()->for($instructor)->create();
        Course::factory()->published()->for($instructor, 'instructor')->create();

        $response = $this->getJson("/api/v1/instructors/{$instructor->id}")->assertOk()
            ->assertJsonPath('data.id', $instructor->id)->assertJsonCount(1, 'data.courses');
        $this->assertStringNotContainsString('private-instructor@example.test', $response->getContent());
        $this->assertStringNotContainsString('0599999999', $response->getContent());
        $response->assertJsonMissingPath('data.roles')->assertJsonMissingPath('data.permissions');
    }

    public function test_unpublished_editorial_pages_and_inactive_faqs_remain_private(): void
    {
        SitePage::factory()->create(['slug' => 'about', 'published_at' => null]);
        SiteFaq::factory()->create(['is_active' => false]);

        $this->getJson('/api/v1/site-pages/about')->assertNotFound();
        $this->getJson('/api/v1/site-faqs')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_published_editorial_content_is_returned_as_plain_data(): void
    {
        SitePage::factory()->create(['slug' => 'about', 'body_ar' => '<script>alert(1)</script>', 'body_en' => 'Approved text', 'published_at' => now()]);

        $this->withHeader('X-Locale', 'en')->getJson('/api/v1/site-pages/about')
            ->assertOk()->assertJsonPath('data.body', 'Approved text');
    }

    public function test_package_savings_are_server_computed_only_from_all_visible_member_courses(): void
    {
        $package = Package::factory()->published()->create(['price' => '120.00']);
        $first = Course::factory()->published()->create(['price' => '100.00']);
        $second = Course::factory()->published()->create(['price' => '50.00']);
        PackageCourse::factory()->for($package)->for($first)->create(['sort_order' => 0]);
        PackageCourse::factory()->for($package)->for($second)->create(['sort_order' => 1]);

        $this->getJson("/api/v1/packages/{$package->slug}")->assertOk()
            ->assertJsonPath('data.savings_vs_individual', '30.00');

        $second->update(['status' => CourseStatus::Draft]);
        $this->getJson("/api/v1/packages/{$package->slug}")->assertOk()
            ->assertJsonPath('data.savings_vs_individual', null);
    }
}
