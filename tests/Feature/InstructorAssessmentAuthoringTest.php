<?php

namespace Tests\Feature;

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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstructorAssessmentAuthoringTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_assigned_instructor_authors_and_publishes_three_question_types_without_access_to_foreign_quiz(): void
    {
        $instructor = $this->instructorWithPermissions([
            PermissionName::CoursesView, PermissionName::AssessmentsView, PermissionName::AssessmentsCreate,
            PermissionName::AssessmentsUpdate, PermissionName::AssessmentsPublish,
        ]);
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $foreignCourse = Course::factory()->create();
        $foreignQuiz = Quiz::factory()->for($foreignCourse)->create();
        $lesson = Lesson::factory()->for(CourseSection::factory()->for($course)->create(), 'section')->create();

        $response = $this->postJson("/api/v1/admin/courses/{$course->id}/quizzes", [
            'lesson_id' => $lesson->id, 'title' => 'Three types', 'instructions' => 'Answer carefully.',
            'passing_score' => 70, 'time_limit_minutes' => 30, 'max_attempts' => 2,
            'show_results' => true, 'show_correct_answers' => false,
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.lesson_id', $lesson->id);
        $quizId = $response->json('data.id');

        $ids = [];
        foreach ([
            ['single_choice', [['answer_text' => 'A', 'is_correct' => true], ['answer_text' => 'B', 'is_correct' => false]]],
            ['multiple_choice', [['answer_text' => 'A', 'is_correct' => true], ['answer_text' => 'B', 'is_correct' => true]]],
            ['true_false', [['answer_text' => 'True', 'is_correct' => true], ['answer_text' => 'False', 'is_correct' => false]]],
        ] as [$type, $options]) {
            $ids[] = $this->postJson("/api/v1/admin/quizzes/{$quizId}/questions", [
                'type' => $type, 'question_text' => "Question {$type}", 'points' => 1, 'options' => $options,
            ])->assertCreated()->json('data.id');
        }

        $this->postJson("/api/v1/admin/quizzes/{$quizId}/questions/reorder", ['ids' => array_reverse($ids)])->assertNoContent();
        $this->postJson("/api/v1/admin/quizzes/{$quizId}/publication")->assertOk()->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.questions.0.id', $ids[2]);
        $this->getJson("/api/v1/instructor/courses/{$course->id}/quizzes")->assertOk()
            ->assertJsonPath('data.0.questions_count', 3)
            ->assertJsonPath('data.0.capabilities.can_publish', true);
        $this->assertDatabaseHas('quizzes', ['id' => $quizId, 'course_id' => $course->id, 'status' => 'published']);

        $this->postJson("/api/v1/admin/courses/{$foreignCourse->id}/quizzes", [
            'title' => 'Foreign', 'passing_score' => 70,
        ])->assertForbidden();
        $this->getJson("/api/v1/admin/courses/{$course->id}/quizzes/{$foreignQuiz->id}")->assertNotFound();
    }

    public function test_assigned_instructor_manages_private_assignment_attachment_but_cannot_edit_foreign_assignment(): void
    {
        Storage::fake('local');
        $instructor = $this->instructorWithPermissions([
            PermissionName::CoursesView, PermissionName::AssignmentsView, PermissionName::AssignmentsCreate,
            PermissionName::AssignmentsUpdate, PermissionName::AssignmentsPublish,
        ]);
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $foreignCourse = Course::factory()->create();
        $foreignAssignment = Assignment::factory()->for($foreignCourse)->create();

        $response = $this->postJson("/api/v1/admin/courses/{$course->id}/assignments", [
            'title' => 'Practical project', 'instructions' => 'Upload your report.', 'submission_type' => 'text_and_file',
            'maximum_score' => 100, 'passing_score' => 60, 'max_attempts' => 2, 'allow_late_submissions' => true,
        ])->assertCreated()->assertJsonPath('data.status', 'draft');
        $assignmentId = $response->json('data.id');

        $upload = $this->post("/api/v1/admin/assignments/{$assignmentId}/attachments", [
            'file' => UploadedFile::fake()->create('brief.pdf', 20, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonMissingPath('data.storage_path');
        $attachmentId = $upload->json('data.id');
        $this->get("/api/v1/admin/assignment-attachments/{$attachmentId}/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-disposition');
        $this->postJson("/api/v1/admin/assignments/{$assignmentId}/publication")->assertOk()->assertJsonPath('data.status', 'published');
        $this->getJson("/api/v1/instructor/courses/{$course->id}/assignments")->assertOk()
            ->assertJsonPath('data.0.capabilities.can_publish', true)
            ->assertJsonPath('data.0.attachments.0.id', $attachmentId);
        $this->assertDatabaseHas('assignments', ['id' => $assignmentId, 'course_id' => $course->id, 'status' => 'published']);

        $this->postJson("/api/v1/admin/courses/{$foreignCourse->id}/assignments", [
            'title' => 'Foreign', 'submission_type' => 'text', 'maximum_score' => 100,
        ])->assertForbidden();
        $this->patchJson("/api/v1/admin/courses/{$course->id}/assignments/{$foreignAssignment->id}", ['title' => 'Wrong course'])
            ->assertNotFound();
    }

    /** @param list<PermissionName> $permissions */
    private function instructorWithPermissions(array $permissions): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $role = Role::query()->where('name', RoleName::Instructor->value)->firstOrFail();
        $role->syncPermissions(array_map(fn (PermissionName $permission): string => $permission->value, $permissions));
        $instructor = User::factory()->create();
        $instructor->assignRole($role);
        Sanctum::actingAs($instructor);

        return $instructor;
    }
}
