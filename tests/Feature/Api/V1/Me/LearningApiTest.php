<?php

namespace Tests\Feature\Api\V1\Me;

use App\LessonProgressStatus;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LearningApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_access_exposes_protected_learning_content_and_resources(): void
    {
        [$user, $course, , $lesson] = $this->createAccessibleCourse();
        LessonResource::factory()->for($lesson)->create(['file_path' => 'private/worksheet.pdf']);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/me/courses/{$course->slug}/learn")
            ->assertOk()
            ->assertJsonPath('data.curriculum.0.lessons.0.content', $lesson->content)
            ->assertJsonPath('data.curriculum.0.lessons.0.video_id', $lesson->video_id)
            ->assertJsonPath('data.curriculum.0.lessons.0.resources.0.file_path', 'private/worksheet.pdf');
    }

    public function test_user_without_valid_access_cannot_retrieve_protected_content(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->published()->for($section, 'section')->create(['content' => 'Protected lesson body']);
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/me/courses/{$course->slug}/learn")
            ->assertForbidden();

        $this->assertStringNotContainsString($lesson->content, $response->getContent());
    }

    #[DataProvider('blockedEnrollmentStates')]
    public function test_expired_or_suspended_user_cannot_retrieve_protected_content(string $state): void
    {
        [$user, $course, $enrollment, $lesson] = $this->createAccessibleCourse();

        if ($state === 'expired') {
            $enrollment->accessGrants()->delete();
            EnrollmentAccessGrant::factory()->for($enrollment)->expired()->create();
        } else {
            $enrollment->update(['status' => 'suspended']);
        }

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/me/courses/{$course->slug}/learn")
            ->assertForbidden();

        $this->assertStringNotContainsString($lesson->content, $response->getContent());
    }

    public function test_user_cannot_use_another_students_enrollment(): void
    {
        [, $course] = $this->createAccessibleCourse();
        $otherUser = User::factory()->create();
        Sanctum::actingAs($otherUser);

        $this->getJson("/api/v1/me/courses/{$course->slug}/learn")
            ->assertForbidden();
    }

    public function test_cross_course_lesson_progress_returns_404(): void
    {
        [$user, $course] = $this->createAccessibleCourse();
        $otherLesson = Lesson::factory()->published()->create();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/courses/{$course->slug}/lessons/{$otherLesson->id}/progress", [
            'watched_seconds' => 10,
        ])->assertNotFound();

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_lesson_progress_is_created_updated_and_preserves_started_at(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment, $lesson] = $this->createAccessibleCourse();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/progress", [
            'watched_seconds' => 40,
            'last_position_seconds' => 30,
        ])->assertOk()
            ->assertJsonPath('data.status', LessonProgressStatus::InProgress->value)
            ->assertJsonPath('data.last_position_seconds', 30);
        $startedAt = LessonProgress::query()->whereBelongsTo($enrollment)->sole()->started_at;

        $this->travel(10)->minutes();
        $this->patchJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/progress", [
            'watched_seconds' => 20,
            'last_position_seconds' => 50,
        ])->assertOk()
            ->assertJsonPath('data.watched_seconds', 40)
            ->assertJsonPath('data.last_position_seconds', 50);

        $progress = LessonProgress::query()->whereBelongsTo($enrollment)->sole();
        $this->assertSame($startedAt?->toISOString(), $progress->started_at?->toISOString());
        $this->assertDatabaseCount('lesson_progress', 1);
    }

    public function test_video_position_cannot_exceed_known_duration(): void
    {
        [$user, $course, , $lesson] = $this->createAccessibleCourse();
        $lesson->update(['duration_seconds' => 300]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/progress", [
            'last_position_seconds' => 301,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('last_position_seconds');

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_negative_progress_values_return_422(): void
    {
        [$user, $course, , $lesson] = $this->createAccessibleCourse();
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/progress", [
            'watched_seconds' => -1,
            'last_position_seconds' => -1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['watched_seconds', 'last_position_seconds']);

        $this->assertDatabaseCount('lesson_progress', 0);
    }

    public function test_lesson_completion_is_idempotent_and_sets_completion_times(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment, $lesson] = $this->createAccessibleCourse();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', LessonProgressStatus::Completed->value);
        $completedAt = LessonProgress::query()->whereBelongsTo($enrollment)->sole()->completed_at;

        $this->travel(10)->minutes();
        $this->postJson("/api/v1/me/courses/{$course->slug}/lessons/{$lesson->id}/complete")
            ->assertOk();

        $progress = LessonProgress::query()->whereBelongsTo($enrollment)->sole();
        $this->assertNotNull($progress->started_at);
        $this->assertSame($completedAt?->toISOString(), $progress->completed_at?->toISOString());
        $this->assertDatabaseCount('lesson_progress', 1);
    }

    public function test_course_percentage_excludes_unpublished_and_inactive_lessons(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $activeSection = CourseSection::factory()->for($course)->create(['is_active' => true]);
        $inactiveSection = CourseSection::factory()->for($course)->inactive()->create();
        $completedLesson = Lesson::factory()->published()->for($activeSection, 'section')->create();
        Lesson::factory()->published()->for($activeSection, 'section')->create();
        Lesson::factory()->for($activeSection, 'section')->create();
        Lesson::factory()->published()->for($inactiveSection, 'section')->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();
        LessonProgress::factory()->completed()->for($enrollment)->for($completedLesson)->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/me/courses/{$course->slug}/progress")
            ->assertOk()
            ->assertJsonPath('data.enrollment.completed_lessons', 1)
            ->assertJsonPath('data.enrollment.total_lessons', 2)
            ->assertJsonPath('data.enrollment.progress_percentage', 50);
    }

    public function test_course_with_no_applicable_lessons_reports_zero_progress(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/me/courses/{$course->slug}/progress")
            ->assertOk()
            ->assertJsonPath('data.enrollment.completed_lessons', 0)
            ->assertJsonPath('data.enrollment.total_lessons', 0)
            ->assertJsonPath('data.enrollment.progress_percentage', 0)
            ->assertJsonPath('data.enrollment.resume', null);
    }

    public function test_resume_returns_most_recently_interacted_lesson(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment, $firstLesson] = $this->createAccessibleCourse();
        $secondLesson = Lesson::factory()->published()->for($firstLesson->section, 'section')->create(['sort_order' => 1]);
        LessonProgress::factory()->for($enrollment)->for($firstLesson)->create(['updated_at' => now()->subHour()]);
        LessonProgress::factory()->for($enrollment)->for($secondLesson)->create([
            'last_position_seconds' => 90,
            'updated_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.resume.lesson.id', $secondLesson->id)
            ->assertJsonPath('data.0.resume.last_position_seconds', 90);
    }

    public function test_resume_falls_back_to_first_published_lesson_in_first_active_section(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $secondSection = CourseSection::factory()->for($course)->create(['sort_order' => 1]);
        $firstSection = CourseSection::factory()->for($course)->create(['sort_order' => 0]);
        Lesson::factory()->published()->for($secondSection, 'section')->create(['sort_order' => 0]);
        $firstLesson = Lesson::factory()->published()->for($firstSection, 'section')->create(['sort_order' => 0]);
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/courses')
            ->assertOk()
            ->assertJsonPath('data.0.resume.lesson.id', $firstLesson->id)
            ->assertJsonPath('data.0.resume.status', LessonProgressStatus::NotStarted->value)
            ->assertJsonPath('data.0.resume.last_position_seconds', 0);
    }

    /** @return array<string, array{string}> */
    public static function blockedEnrollmentStates(): array
    {
        return [
            'expired enrollment' => ['expired'],
            'suspended enrollment' => ['suspended'],
        ];
    }

    /** @return array{User, Course, Enrollment, Lesson} */
    private function createAccessibleCourse(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->published()->video()->for($section, 'section')->create([
            'content' => 'Protected supporting notes.',
            'duration_seconds' => 300,
        ]);
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        return [$user, $course, $enrollment, $lesson];
    }
}
