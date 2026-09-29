<?php

namespace Tests\Feature\Api\V1\Admin;

use App\LessonType;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CurriculumApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('authorizedRoles')]
    public function test_authorized_admin_and_content_manager_can_create_sections(string $role): void
    {
        $this->authenticateAs($role);
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/sections", [
            'title' => 'Introduction',
            'sort_order' => 0,
        ])->assertCreated()
            ->assertJsonPath('data.title', 'Introduction');

        $this->assertDatabaseHas('course_sections', ['course_id' => $course->id, 'title' => 'Introduction']);
    }

    public function test_unauthorized_student_cannot_manage_curriculum(): void
    {
        $this->authenticateAs(RoleName::Student->value);
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title' => 'Forbidden'])
            ->assertForbidden();

        $this->assertDatabaseMissing('course_sections', ['title' => 'Forbidden']);
    }

    public function test_section_ownership_is_enforced_by_scoped_binding(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $course = Course::factory()->create();
        $otherSection = CourseSection::factory()->create();

        $this->patchJson("/api/v1/admin/courses/{$course->id}/sections/{$otherSection->id}", ['title' => 'Moved'])
            ->assertNotFound();

        $this->assertDatabaseMissing('course_sections', ['id' => $otherSection->id, 'title' => 'Moved']);
    }

    public function test_lesson_ownership_is_enforced_by_scoped_binding(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $section = CourseSection::factory()->create();
        $otherLesson = Lesson::factory()->create();

        $this->patchJson("/api/v1/admin/sections/{$section->id}/lessons/{$otherLesson->id}", ['title' => 'Moved'])
            ->assertNotFound();

        $this->assertDatabaseMissing('lessons', ['id' => $otherLesson->id, 'title' => 'Moved']);
    }

    public function test_authorized_user_can_manage_lessons_and_resources(): void
    {
        $this->authenticateAs(RoleName::ContentManager->value);
        $section = CourseSection::factory()->create();

        $lessonResponse = $this->postJson("/api/v1/admin/sections/{$section->id}/lessons", [
            'title' => 'Reference Link',
            'slug' => 'reference-link',
            'type' => LessonType::Link->value,
            'video_url' => 'https://example.test/reference',
            'is_published' => true,
        ])->assertCreated();
        $lessonId = $lessonResponse->json('data.id');

        $this->postJson("/api/v1/admin/lessons/{$lessonId}/resources", [
            'title' => 'Worksheet',
            'type' => 'pdf',
            'file_path' => 'lesson-resources/worksheet.pdf',
        ])->assertCreated()
            ->assertJsonPath('data.download_available', true)
            ->assertJsonMissingPath('data.file_path');

        $this->assertDatabaseHas('lessons', ['id' => $lessonId, 'course_section_id' => $section->id]);
        $this->assertDatabaseHas('lesson_resources', ['lesson_id' => $lessonId, 'title' => 'Worksheet']);
    }

    public function test_every_admin_resource_response_omits_storage_metadata(): void
    {
        $this->authenticateAs(RoleName::ContentManager->value);
        $lesson = Lesson::factory()->published()->create();
        $resource = LessonResource::factory()->for($lesson)->create(['file_path' => 'lesson-resources/secret.pdf']);
        $section = $lesson->section;

        $responses = [
            $this->getJson("/api/v1/admin/lessons/{$lesson->id}/resources")->assertOk(),
            $this->getJson("/api/v1/admin/lessons/{$lesson->id}/resources/{$resource->id}")->assertOk(),
            $this->patchJson("/api/v1/admin/lessons/{$lesson->id}/resources/{$resource->id}", ['title' => 'Updated worksheet'])->assertOk(),
            $this->postJson("/api/v1/admin/lessons/{$lesson->id}/resources/reorder", ['ids' => [$resource->id]])->assertOk(),
            $this->getJson("/api/v1/admin/sections/{$section->id}/lessons")->assertOk(),
            $this->getJson("/api/v1/admin/sections/{$section->id}/lessons/{$lesson->id}")->assertOk(),
            $this->getJson("/api/v1/admin/courses/{$section->course_id}/sections/{$section->id}")->assertOk(),
        ];

        foreach ($responses as $response) {
            foreach (['file_path', 'storage_path', 'storage_disk', 'secret.pdf'] as $metadata) {
                $this->assertStringNotContainsString($metadata, $response->getContent());
            }
        }
    }

    public function test_admin_cannot_write_traversal_paths_or_non_http_resource_urls(): void
    {
        $this->authenticateAs(RoleName::ContentManager->value);
        $lesson = Lesson::factory()->create();
        $resource = LessonResource::factory()->for($lesson)->create();
        $payload = ['title' => 'Worksheet', 'type' => 'pdf', 'file_path' => 'lesson-resources/../secret.pdf'];

        $this->postJson("/api/v1/admin/lessons/{$lesson->id}/resources", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('file_path');
        $this->patchJson("/api/v1/admin/lessons/{$lesson->id}/resources/{$resource->id}", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('file_path');
        $this->postJson("/api/v1/admin/lessons/{$lesson->id}/resources", [
            'title' => 'Unsafe', 'type' => 'link', 'external_url' => 'ftp://example.test/file',
        ])->assertUnprocessable()->assertJsonValidationErrors('external_url');
        $this->assertDatabaseCount('lesson_resources', 1);
    }

    public function test_section_reorder_assigns_deterministic_zero_based_positions(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $course = Course::factory()->create();
        $first = CourseSection::factory()->for($course)->create(['sort_order' => 0]);
        $second = CourseSection::factory()->for($course)->create(['sort_order' => 1]);

        $this->postJson("/api/v1/admin/courses/{$course->id}/sections/reorder", ['ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id)
            ->assertJsonPath('data.0.sort_order', 0)
            ->assertJsonPath('data.1.sort_order', 1);

        $this->assertDatabaseHas('course_sections', ['id' => $second->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('course_sections', ['id' => $first->id, 'sort_order' => 1]);
    }

    public function test_lesson_reorder_assigns_deterministic_zero_based_positions(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $section = CourseSection::factory()->create();
        $first = Lesson::factory()->for($section, 'section')->create(['sort_order' => 0]);
        $second = Lesson::factory()->for($section, 'section')->create(['sort_order' => 1]);

        $this->postJson("/api/v1/admin/sections/{$section->id}/lessons/reorder", ['ids' => [$second->id, $first->id]])
            ->assertOk()
            ->assertJsonPath('data.0.id', $second->id);

        $this->assertDatabaseHas('lessons', ['id' => $second->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('lessons', ['id' => $first->id, 'sort_order' => 1]);
    }

    public function test_cross_parent_reorder_manipulation_returns_422_without_changes(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $course = Course::factory()->create();
        $owned = CourseSection::factory()->for($course)->create(['sort_order' => 4]);
        $foreign = CourseSection::factory()->create(['sort_order' => 7]);

        $this->postJson("/api/v1/admin/courses/{$course->id}/sections/reorder", ['ids' => [$owned->id, $foreign->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->assertDatabaseHas('course_sections', ['id' => $owned->id, 'sort_order' => 4]);
        $this->assertDatabaseHas('course_sections', ['id' => $foreign->id, 'sort_order' => 7]);
    }

    #[DataProvider('invalidLessonPayloads')]
    public function test_lesson_type_validation_returns_422(array $payload, string $field): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $section = CourseSection::factory()->create();

        $this->postJson("/api/v1/admin/sections/{$section->id}/lessons", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_same_lesson_slug_is_allowed_in_different_sections(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $firstSection = CourseSection::factory()->create();
        $secondSection = CourseSection::factory()->create();
        Lesson::factory()->for($firstSection, 'section')->create(['slug' => 'welcome']);

        $this->postJson("/api/v1/admin/sections/{$secondSection->id}/lessons", [
            'title' => 'Welcome',
            'slug' => 'welcome',
            'type' => LessonType::Text->value,
            'content' => 'Welcome to another course section.',
        ])->assertCreated();

        $this->assertDatabaseCount('lessons', 2);
    }

    /** @return array<string, array{string}> */
    public static function authorizedRoles(): array
    {
        return [
            'admin' => [RoleName::Admin->value],
            'content manager' => [RoleName::ContentManager->value],
        ];
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidLessonPayloads(): array
    {
        return [
            'video without source' => [[
                'title' => 'Video',
                'slug' => 'video',
                'type' => LessonType::Video->value,
            ], 'video_url'],
            'text without content' => [[
                'title' => 'Text',
                'slug' => 'text',
                'type' => LessonType::Text->value,
            ], 'content'],
            'link without URL' => [[
                'title' => 'Link',
                'slug' => 'link',
                'type' => LessonType::Link->value,
            ], 'video_url'],
        ];
    }

    private function authenticateAs(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
