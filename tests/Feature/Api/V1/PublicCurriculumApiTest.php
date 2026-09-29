<?php

namespace Tests\Feature\Api\V1;

use App\LessonType;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicCurriculumApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_course_exposes_only_active_sections(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'public-course']);
        CourseSection::factory()->for($course)->create(['title' => 'Visible Section']);
        CourseSection::factory()->inactive()->for($course)->create(['title' => 'Hidden Section']);

        $this->getJson('/api/v1/courses/public-course')
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.title', 'Visible Section')
            ->assertJsonCount(1, 'data.curriculum')
            ->assertJsonMissing(['title' => 'Hidden Section']);
    }

    public function test_public_curriculum_exposes_only_published_lessons(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'lesson-visibility']);
        $section = CourseSection::factory()->for($course)->create();
        Lesson::factory()->published()->for($section, 'section')->create(['title' => 'Published Lesson', 'slug' => 'published']);
        Lesson::factory()->for($section, 'section')->create(['title' => 'Draft Lesson', 'slug' => 'draft']);

        $this->getJson('/api/v1/courses/lesson-visibility')
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.lessons.0.title', 'Published Lesson')
            ->assertJsonCount(1, 'data.curriculum.0.lessons')
            ->assertJsonMissing(['title' => 'Draft Lesson']);
    }

    public function test_non_preview_lesson_does_not_leak_protected_content_or_resources(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'protected-content']);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->published()->video()->for($section, 'section')->create([
            'slug' => 'protected-lesson',
            'content' => 'Private lesson body',
            'video_id' => 'private-video-id',
            'video_url' => 'https://videos.example.test/private',
        ]);
        LessonResource::factory()->for($lesson)->create(['file_path' => 'private/exercise.pdf']);

        $response = $this->getJson('/api/v1/courses/protected-content')->assertOk();
        $publicLesson = $response->json('data.curriculum.0.lessons.0');

        $this->assertSame('protected-lesson', $publicLesson['slug']);
        $this->assertArrayNotHasKey('content', $publicLesson);
        $this->assertArrayNotHasKey('video_id', $publicLesson);
        $this->assertArrayNotHasKey('video_url', $publicLesson);
        $this->assertArrayNotHasKey('resources', $publicLesson);
        $response->assertJsonMissing(['file_path' => 'private/exercise.pdf']);
    }

    public function test_preview_lesson_exposes_preview_content_without_resources(): void
    {
        $course = Course::factory()->published()->create(['slug' => 'preview-content']);
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->preview()->for($section, 'section')->create([
            'slug' => 'preview-lesson',
            'type' => LessonType::Text,
            'content' => 'Public preview body',
        ]);
        LessonResource::factory()->for($lesson)->create(['file_path' => 'private/preview-handout.pdf']);

        $this->getJson('/api/v1/courses/preview-content')
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.lessons.0.content', 'Public preview body')
            ->assertJsonMissingPath('data.curriculum.0.lessons.0.resources')
            ->assertJsonMissing(['file_path' => 'private/preview-handout.pdf']);
    }
}
