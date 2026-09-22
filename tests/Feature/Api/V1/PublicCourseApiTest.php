<?php

namespace Tests\Feature\Api\V1;

use App\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicCourseApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_returns_only_currently_published_courses(): void
    {
        $published = Course::factory()->published()->create(['title' => 'Visible Course', 'slug' => 'visible-course']);
        Course::factory()->create(['title' => 'Draft Course', 'slug' => 'draft-course']);
        Course::factory()->published()->create(['title' => 'Future Course', 'slug' => 'future-course', 'published_at' => now()->addDay()]);

        $this->getJson('/api/v1/courses')
            ->assertOk()
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonCount(1, 'data')
            ->assertJsonMissing(['slug' => 'draft-course'])
            ->assertJsonMissing(['slug' => 'future-course']);
    }

    public function test_returns_published_course_details_by_slug(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'laravel-api']);
        CourseLearningOutcome::factory()->for($course)->create(['outcome' => 'Build an API.']);

        $this->getJson('/api/v1/courses/laravel-api')
            ->assertOk()
            ->assertJsonPath('data.slug', 'laravel-api')
            ->assertJsonPath('data.learning_outcomes.0.outcome', 'Build an API.');
    }

    public function test_returns_404_for_unpublished_course_details(): void
    {
        $course = Course::factory()->create(['status' => CourseStatus::Draft, 'slug' => 'draft']);

        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertNotFound();
    }

    public function test_status_filter_cannot_expose_unpublished_courses(): void
    {
        Course::factory()->create(['status' => CourseStatus::Draft]);

        $this->getJson('/api/v1/courses?status=draft')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filters_published_courses_by_search_category_instructor_level_and_language(): void
    {
        $category = Category::factory()->create(['slug' => 'technology']);
        $instructor = User::factory()->create();
        Course::factory()->published()->for($category)->for($instructor, 'instructor')->create([
            'title' => 'Advanced Laravel',
            'slug' => 'advanced-laravel',
            'level' => 'advanced',
            'language' => 'ar',
        ]);
        Course::factory()->published()->create(['title' => 'Project Management']);

        $this->getJson("/api/v1/courses?search=Laravel&category=technology&instructor={$instructor->id}&level=advanced&language=ar")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'advanced-laravel');
    }
}
