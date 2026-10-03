<?php

namespace Tests\Feature\Api\V1\Instructor;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstructorPortalApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_courses_and_dashboard_are_scoped_to_assigned_instructor(): void
    {
        $instructor = $this->instructor();
        $own = Course::factory()->published()->for($instructor, 'instructor')->create(['title' => 'Owned Course']);
        $foreign = Course::factory()->create(['title' => 'Foreign Course']);
        Assignment::factory()->for($own)->create();

        $this->getJson('/api/v1/instructor/dashboard-summary')->assertOk()
            ->assertJsonPath('data.courses', 1)
            ->assertJsonPath('data.published_courses', 1)
            ->assertJsonPath('data.draft_courses', 0)
            ->assertJsonPath('data.awaiting_grading', 0);
        $this->getJson('/api/v1/instructor/courses?search=Owned')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/v1/instructor/courses?search=Foreign')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/instructor/courses/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/admin/courses/{$foreign->id}")->assertForbidden();
        $this->getJson('/api/v1/admin/courses')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/courses/{$foreign->id}/sections")->assertForbidden();
        $this->getJson('/api/v1/admin/dashboard-summary')->assertForbidden();
    }

    public function test_quiz_and_assignment_reads_reject_foreign_identifiers(): void
    {
        $instructor = $this->instructor();
        $own = Course::factory()->for($instructor, 'instructor')->create();
        $foreign = Course::factory()->create();
        $ownQuiz = Quiz::factory()->for($own)->create();
        $foreignQuiz = Quiz::factory()->for($foreign)->create();
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->for($own)->create();
        $attempt = QuizAttempt::factory()->submitted()->for($ownQuiz)->for($enrollment)->for($student)->create();
        $foreignEnrollment = Enrollment::factory()->for($student)->for($foreign)->create();
        $foreignAttempt = QuizAttempt::factory()->submitted()->for($foreignQuiz)->for($foreignEnrollment)->for($student)->create();
        $assignment = Assignment::factory()->for($own)->create();
        $foreignAssignment = Assignment::factory()->for($foreign)->create();

        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes")->assertOk()->assertJsonPath('data.0.id', $ownQuiz->id);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes/{$ownQuiz->id}/attempts")->assertOk()->assertJsonPath('data.0.id', $attempt->id);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes/{$ownQuiz->id}/attempts/{$attempt->id}")->assertOk();
        $unfinished = QuizAttempt::factory()->for($ownQuiz)->for($enrollment)->for($student)->create(['attempt_number' => 2]);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes/{$ownQuiz->id}/attempts/{$unfinished->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$foreign->id}/quizzes/{$foreignQuiz->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes/{$foreignQuiz->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$own->id}/quizzes/{$ownQuiz->id}/attempts/{$foreignAttempt->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments")->assertOk()->assertJsonPath('data.0.id', $assignment->id);
        $this->getJson("/api/v1/instructor/courses/{$foreign->id}/assignments/{$foreignAssignment->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments/{$foreignAssignment->id}")->assertNotFound();
    }

    public function test_existing_admin_curriculum_reads_are_owner_scoped(): void
    {
        $instructor = $this->instructor();
        $own = Course::factory()->for($instructor, 'instructor')->create();
        $foreign = Course::factory()->create();
        $ownSection = CourseSection::factory()->for($own)->create();
        $foreignSection = CourseSection::factory()->for($foreign)->create();
        $foreignLesson = Lesson::factory()->for($foreignSection, 'section')->create();
        $foreignResource = LessonResource::factory()->for($foreignLesson, 'lesson')->create();

        $this->getJson("/api/v1/instructor/courses/{$own->id}/curriculum")->assertOk()->assertJsonPath('data.0.id', $ownSection->id);
        $this->getJson("/api/v1/instructor/courses/{$foreign->id}/curriculum")->assertNotFound();
        $this->getJson("/api/v1/admin/courses/{$foreign->id}/sections")->assertForbidden();
        $this->getJson("/api/v1/admin/courses/{$foreign->id}/sections/{$foreignSection->id}")->assertForbidden();
        $this->getJson("/api/v1/admin/sections/{$foreignSection->id}/lessons")->assertForbidden();
        $this->getJson("/api/v1/admin/sections/{$foreignSection->id}/lessons/{$foreignLesson->id}")->assertForbidden();
        $this->getJson("/api/v1/admin/lessons/{$foreignLesson->id}/resources")->assertForbidden();
        $this->getJson("/api/v1/admin/lessons/{$foreignLesson->id}/resources/{$foreignResource->id}")->assertForbidden();
    }

    public function test_submissions_and_grading_operations_reject_foreign_instructor(): void
    {
        Storage::fake('local');
        $instructor = $this->instructor();
        $own = Course::factory()->for($instructor, 'instructor')->create();
        $foreign = Course::factory()->create();
        $assignment = Assignment::factory()->for($own)->create();
        $foreignAssignment = Assignment::factory()->for($foreign)->create();
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->for($student)->for($own)->create();
        $foreignEnrollment = Enrollment::factory()->for($student)->for($foreign)->create();
        $submission = AssignmentSubmission::factory()->submitted()->for($assignment)->for($enrollment)->for($student)->create();
        $foreignSubmission = AssignmentSubmission::factory()->submitted()->for($foreignAssignment)->for($foreignEnrollment)->for($student)->create();
        $file = AssignmentSubmissionFile::factory()->for($foreignSubmission, 'submission')->create();
        $ownFile = AssignmentSubmissionFile::factory()->for($submission, 'submission')->create([
            'storage_path' => "assignment-submissions/{$submission->id}/work.pdf",
        ]);
        Storage::disk('local')->put($ownFile->storage_path, 'private work');

        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments/{$assignment->id}/submissions")->assertOk()->assertJsonPath('data.0.id', $submission->id);
        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments/{$assignment->id}/submissions/{$submission->id}")
            ->assertOk()->assertJsonPath('data.files.0.id', $ownFile->id)->assertJsonMissingPath('data.files.0.storage_path');
        $this->get("/api/v1/admin/assignment-submission-files/{$ownFile->id}/download", ['Accept' => 'application/json'])
            ->assertOk()->assertHeader('content-disposition');
        $this->getJson("/api/v1/instructor/courses/{$own->id}/assignments/{$assignment->id}/submissions/{$foreignSubmission->id}")->assertNotFound();
        $this->getJson("/api/v1/instructor/courses/{$foreign->id}/assignments/{$foreignAssignment->id}/submissions/{$foreignSubmission->id}")->assertNotFound();
        $this->getJson("/api/v1/admin/assignment-submission-files/{$file->id}/download")->assertForbidden();
        $this->postJson("/api/v1/admin/assignment-submissions/{$foreignSubmission->id}/grade", ['score' => 70])->assertForbidden();
        $this->postJson("/api/v1/admin/assignment-submissions/{$foreignSubmission->id}/revision", ['feedback' => 'Revise'])->assertForbidden();
        $this->postJson("/api/v1/admin/assignment-submissions/{$foreignSubmission->id}/grade-corrections", ['score' => 80, 'reason' => 'Correction'])->assertForbidden();
    }

    public function test_assigned_instructor_can_grade_correct_and_request_revision(): void
    {
        $instructor = $this->instructor();
        $course = Course::factory()->for($instructor, 'instructor')->create();
        $assignment = Assignment::factory()->for($course)->create();
        $firstStudent = User::factory()->create();
        $secondStudent = User::factory()->create();
        $firstEnrollment = Enrollment::factory()->for($firstStudent)->for($course)->create();
        $secondEnrollment = Enrollment::factory()->for($secondStudent)->for($course)->create();
        $grading = AssignmentSubmission::factory()->submitted()->for($assignment)->for($firstEnrollment)->for($firstStudent)->create();
        $revising = AssignmentSubmission::factory()->submitted()->for($assignment)->for($secondEnrollment)->for($secondStudent)->create();

        $this->postJson("/api/v1/admin/assignment-submissions/{$grading->id}/grade", ['score' => '70.50'])
            ->assertOk()->assertJsonPath('data.status', 'graded');
        $this->postJson("/api/v1/admin/assignment-submissions/{$grading->id}/grade-corrections", [
            'score' => '75.25', 'reason' => 'Rubric correction',
        ])->assertOk()->assertJsonPath('data.score', '75.25')->assertJsonCount(2, 'data.grading_history');
        $this->postJson("/api/v1/admin/assignment-submissions/{$revising->id}/revision", [
            'feedback' => 'Please improve the analysis.',
        ])->assertOk()->assertJsonPath('data.status', 'revision_requested');
        $this->assertDatabaseHas('assignment_submissions', ['id' => $grading->id, 'score' => '75.25']);
        $this->assertDatabaseHas('assignment_submissions', ['id' => $revising->id, 'status' => 'revision_requested']);
    }

    public function test_admin_and_content_manager_keep_existing_course_and_content_reads(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $course = Course::factory()->create();
        $section = CourseSection::factory()->for($course)->create();
        $quiz = Quiz::factory()->for($course)->create();
        $assignment = Assignment::factory()->for($course)->create();

        foreach ([RoleName::Admin, RoleName::ContentManager] as $role) {
            $staff = User::factory()->create();
            $staff->assignRole($role->value);
            Sanctum::actingAs($staff);

            $this->getJson("/api/v1/admin/courses/{$course->id}")->assertOk();
            $this->getJson("/api/v1/admin/courses/{$course->id}/sections")->assertOk()->assertJsonPath('data.0.id', $section->id);
            $this->getJson("/api/v1/admin/courses/{$course->id}/quizzes")->assertOk()->assertJsonPath('data.0.id', $quiz->id);
            $this->getJson("/api/v1/admin/courses/{$course->id}/assignments")->assertOk()->assertJsonPath('data.0.id', $assignment->id);
        }
    }

    private function instructor(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(RoleName::Instructor->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
