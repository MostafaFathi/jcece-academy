<?php

namespace Tests\Feature;

use App\AssignmentSubmissionType;
use App\LessonType;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DynamicCurriculumPermissionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_role_update_changes_instructor_capabilities_and_curriculum_writes_on_new_requests(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $first = CourseSection::factory()->for($course)->create(['sort_order' => 0]);
        $second = CourseSection::factory()->for($course)->create(['sort_order' => 1]);

        Sanctum::actingAs($instructor);
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonMissing([PermissionName::CurriculumCreate->value]);
        $this->getJson("/api/v1/instructor/courses/{$course->id}")->assertOk()
            ->assertJsonPath('data.capabilities.can_view_curriculum', true)
            ->assertJsonPath('data.capabilities.can_create_curriculum', false)
            ->assertJsonPath('data.capabilities.can_update_curriculum', false)
            ->assertJsonPath('data.capabilities.can_delete_curriculum', false);
        $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title' => 'Denied'])->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$course->id}/sections/{$first->id}", ['title' => 'Denied'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/courses/{$course->id}/sections/{$second->id}")->assertForbidden();
        $this->postJson("/api/v1/admin/courses/{$course->id}/sections/reorder", ['ids' => [$second->id, $first->id]])->assertForbidden();

        Sanctum::actingAs($admin);
        $snapshot = $this->getJson('/api/v1/admin/roles/instructor')->assertOk()->json();
        $this->putJson('/api/v1/admin/roles/instructor/permissions', [
            'permissions' => [...$snapshot['permissions'], PermissionName::CurriculumCreate->value, PermissionName::CurriculumUpdate->value, PermissionName::CurriculumDelete->value],
            'version' => $snapshot['version'],
        ])->assertOk();

        Sanctum::actingAs($instructor->fresh());
        $this->getJson('/api/v1/auth/user')->assertOk()->assertJsonFragment([PermissionName::CurriculumCreate->value]);
        $this->getJson("/api/v1/instructor/courses/{$course->id}")->assertOk()
            ->assertJsonPath('data.capabilities.can_create_curriculum', true)
            ->assertJsonPath('data.capabilities.can_update_curriculum', true)
            ->assertJsonPath('data.capabilities.can_delete_curriculum', true);
        $created = $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title' => 'New section'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/admin/courses/{$course->id}/sections/{$created}", ['title' => 'Updated section'])
            ->assertOk()->assertJsonPath('data.title', 'Updated section');
        $this->postJson("/api/v1/admin/courses/{$course->id}/sections/reorder", ['ids' => [$created, $first->id, $second->id]])
            ->assertOk()->assertJsonPath('data.0.id', $created);
        $this->deleteJson("/api/v1/admin/courses/{$course->id}/sections/{$second->id}")->assertNoContent();
        $this->assertDatabaseHas('course_sections', ['id' => $created, 'title' => 'Updated section', 'sort_order' => 0]);
        $this->assertDatabaseMissing('course_sections', ['id' => $second->id]);

        Sanctum::actingAs($admin);
        $updated = $this->getJson('/api/v1/admin/roles/instructor')->assertOk()->json();
        $this->putJson('/api/v1/admin/roles/instructor/permissions', [
            'permissions' => array_values(array_diff($updated['permissions'], [PermissionName::CurriculumCreate->value])),
            'version' => $updated['version'],
        ])->assertOk();
        Sanctum::actingAs($instructor->fresh());
        $this->getJson("/api/v1/instructor/courses/{$course->id}")->assertOk()
            ->assertJsonPath('data.capabilities.can_create_curriculum', false)
            ->assertJsonPath('data.capabilities.can_update_curriculum', true);
        $this->postJson("/api/v1/admin/courses/{$course->id}/sections", ['title' => 'Denied again'])->assertForbidden();
        $this->assertDatabaseMissing('course_sections', ['title' => 'Denied again']);
    }

    public function test_granted_permissions_never_allow_cross_instructor_or_mixed_parent_writes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $other = User::factory()->create();
        $other->assignRole(RoleName::Instructor->value);
        $ownCourse = Course::factory()->for($instructor, 'instructor')->create();
        $foreignCourse = Course::factory()->for($other, 'instructor')->create();
        $ownSection = CourseSection::factory()->for($ownCourse)->create();
        $foreignSection = CourseSection::factory()->for($foreignCourse)->create();
        $foreignLesson = Lesson::factory()->for($foreignSection, 'section')->create();
        $instructor->givePermissionTo([
            PermissionName::CurriculumCreate->value,
            PermissionName::CurriculumUpdate->value,
            PermissionName::CurriculumDelete->value,
        ]);

        Sanctum::actingAs($instructor);
        $this->getJson("/api/v1/instructor/courses/{$foreignCourse->id}")->assertNotFound();
        $this->getJson("/api/v1/admin/courses/{$foreignCourse->id}")->assertForbidden();
        $this->postJson("/api/v1/admin/courses/{$foreignCourse->id}/sections", ['title' => 'Injected'])->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$foreignCourse->id}/sections/{$foreignSection->id}", ['title' => 'Injected'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/courses/{$foreignCourse->id}/sections/{$foreignSection->id}")->assertForbidden();
        $this->postJson("/api/v1/admin/courses/{$foreignCourse->id}/sections/reorder", ['ids' => [$foreignSection->id]])->assertForbidden();
        $this->postJson("/api/v1/admin/sections/{$foreignSection->id}/lessons", [
            'title' => 'Injected', 'slug' => 'injected', 'type' => LessonType::Text->value, 'content' => 'Unsafe',
        ])->assertForbidden();
        $this->patchJson("/api/v1/admin/sections/{$foreignSection->id}/lessons/{$foreignLesson->id}", ['title' => 'Injected'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/sections/{$foreignSection->id}/lessons/{$foreignLesson->id}")->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$ownCourse->id}/sections/{$foreignSection->id}", ['title' => 'Mixed'])->assertNotFound();
        $this->patchJson("/api/v1/admin/sections/{$ownSection->id}/lessons/{$foreignLesson->id}", ['title' => 'Mixed'])->assertNotFound();
        $this->assertDatabaseHas('course_sections', ['id' => $foreignSection->id, 'title' => $foreignSection->title]);
        $this->assertDatabaseHas('lessons', ['id' => $foreignLesson->id, 'title' => $foreignLesson->title]);
    }

    public function test_granted_instructor_can_manage_own_lessons_and_resources_but_not_foreign_resources(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $instructor->givePermissionTo([
            PermissionName::CurriculumCreate->value,
            PermissionName::CurriculumUpdate->value,
            PermissionName::CurriculumDelete->value,
        ]);
        $ownCourse = Course::factory()->for($instructor, 'instructor')->create();
        $ownSection = CourseSection::factory()->for($ownCourse)->create();
        $foreignLesson = Lesson::factory()->create();

        Sanctum::actingAs($instructor);
        $lesson = $this->postJson("/api/v1/admin/sections/{$ownSection->id}/lessons", [
            'title' => 'Owned lesson', 'slug' => 'owned-lesson', 'type' => LessonType::Text->value, 'content' => 'Content',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/admin/sections/{$ownSection->id}/lessons/{$lesson}", ['title' => 'Updated lesson'])
            ->assertOk()->assertJsonPath('data.title', 'Updated lesson');
        $resource = $this->postJson("/api/v1/admin/lessons/{$lesson}/resources", [
            'title' => 'Guide', 'type' => 'link', 'external_url' => 'https://example.test/guide',
        ])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/admin/lessons/{$lesson}/resources/{$resource}", ['title' => 'Updated guide'])
            ->assertOk()->assertJsonPath('data.title', 'Updated guide');
        $this->postJson("/api/v1/admin/lessons/{$lesson}/resources/reorder", ['ids' => [$resource]])->assertOk();
        $this->postJson("/api/v1/admin/lessons/{$foreignLesson->id}/resources", [
            'title' => 'Injected', 'type' => 'link', 'external_url' => 'https://example.test/guide',
        ])->assertForbidden();
        $this->deleteJson("/api/v1/admin/lessons/{$lesson}/resources/{$resource}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/sections/{$ownSection->id}/lessons/{$lesson}")->assertNoContent();
        $this->assertSoftDeleted('lessons', ['id' => $lesson]);
        $this->assertDatabaseMissing('lesson_resources', ['id' => $resource]);
    }

    public function test_quiz_and_assignment_definition_grants_allow_only_assigned_course_api_writes(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        $instructor->givePermissionTo([
            PermissionName::AssessmentsView->value,
            PermissionName::AssessmentsCreate->value,
            PermissionName::AssessmentsUpdate->value,
            PermissionName::AssessmentsDelete->value,
            PermissionName::AssignmentsView->value,
            PermissionName::AssignmentsCreate->value,
            PermissionName::AssignmentsUpdate->value,
            PermissionName::AssignmentsDelete->value,
        ]);
        $own = Course::factory()->for($instructor, 'instructor')->create();
        $foreign = Course::factory()->create();
        $ownQuiz = Quiz::factory()->for($own)->create();
        $foreignQuiz = Quiz::factory()->for($foreign)->create();
        $ownAssignment = Assignment::factory()->for($own)->create();
        $foreignAssignment = Assignment::factory()->for($foreign)->create();

        Sanctum::actingAs($instructor);
        $this->getJson("/api/v1/instructor/courses/{$own->id}")->assertOk()
            ->assertJsonPath('data.capabilities.can_create_quiz', true)
            ->assertJsonPath('data.capabilities.can_create_assignment', true);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes")->assertOk()
            ->assertJsonPath('data.0.capabilities.can_update', true)
            ->assertJsonPath('data.0.capabilities.can_delete', true);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments")->assertOk()
            ->assertJsonPath('data.0.capabilities.can_update', true)
            ->assertJsonPath('data.0.capabilities.can_delete', true);
        $this->postJson("/api/v1/admin/courses/{$own->id}/quizzes", ['title' => 'Own quiz', 'passing_score' => 70])->assertCreated();
        $this->patchJson("/api/v1/admin/courses/{$own->id}/quizzes/{$ownQuiz->id}", ['title' => 'Updated quiz'])->assertOk();
        $this->postJson("/api/v1/admin/courses/{$own->id}/assignments", [
            'title' => 'Own assignment', 'submission_type' => AssignmentSubmissionType::Text->value, 'maximum_score' => 100,
        ])->assertCreated();
        $this->patchJson("/api/v1/admin/courses/{$own->id}/assignments/{$ownAssignment->id}", ['title' => 'Updated assignment'])->assertOk();
        $this->postJson("/api/v1/admin/courses/{$foreign->id}/quizzes", ['title' => 'Injected quiz', 'passing_score' => 70])->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$foreign->id}/quizzes/{$foreignQuiz->id}", ['title' => 'Injected'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/courses/{$foreign->id}/quizzes/{$foreignQuiz->id}")->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$own->id}/quizzes/{$foreignQuiz->id}", ['title' => 'Mixed quiz'])->assertNotFound();
        $this->postJson("/api/v1/admin/courses/{$foreign->id}/assignments", [
            'title' => 'Injected assignment', 'submission_type' => AssignmentSubmissionType::Text->value, 'maximum_score' => 100,
        ])->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$foreign->id}/assignments/{$foreignAssignment->id}", ['title' => 'Injected'])->assertForbidden();
        $this->deleteJson("/api/v1/admin/courses/{$foreign->id}/assignments/{$foreignAssignment->id}")->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$own->id}/assignments/{$foreignAssignment->id}", ['title' => 'Mixed assignment'])->assertNotFound();
        $this->assertDatabaseHas('quizzes', ['id' => $foreignQuiz->id, 'title' => $foreignQuiz->title]);
        $this->assertDatabaseHas('assignments', ['id' => $foreignAssignment->id, 'title' => $foreignAssignment->title]);
    }
}
