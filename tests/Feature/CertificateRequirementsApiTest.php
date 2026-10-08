<?php

namespace Tests\Feature;

use App\Contracts\CertificatePdfGenerator;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateRequirementsApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->app->instance(CertificatePdfGenerator::class, new class implements CertificatePdfGenerator
        {
            public function generate(Certificate $certificate, string $verificationUrl): string
            {
                return '%PDF-1.4 test';
            }
        });
    }

    public function test_configuration_is_required_and_lesson_threshold_is_exact(): void
    {
        [$student, $course, $enrollment, $lessons] = $this->courseWithAccess();
        Sanctum::actingAs($student);
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.eligible', false)
            ->assertJsonFragment(['certificate_configuration_missing']);

        $course->forceFill(['certificate_required_lesson_percentage' => '50.00', 'certificate_requirements_version' => 1])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['lessons_incomplete']);
        LessonProgress::factory()->completed()->for($enrollment)->for($lessons[0])->create();
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.progress.progress_percentage', 50);

        $course->forceFill(['certificate_enabled' => false])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['certificates_disabled']);
    }

    public function test_final_exam_uses_explicit_quiz_and_configured_passing_threshold(): void
    {
        [$student, $course, $enrollment] = $this->courseWithAccess();
        $quiz = Quiz::factory()->published()->for($course)->create();
        $course->forceFill([
            'certificate_required_lesson_percentage' => '0.00',
            'certificate_requirements_version' => 1,
            'certificate_final_exam_required' => true,
            'certificate_final_exam_quiz_id' => $quiz->id,
            'certificate_final_exam_passing_percentage' => '70.00',
        ])->save();
        Sanctum::actingAs($student);
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['final_exam_incomplete']);
        $attempt = QuizAttempt::factory()->submitted()->for($quiz)->for($enrollment)->for($student, 'user')->create(['percentage' => '69.99']);
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['final_exam_failed']);
        $attempt->forceFill(['percentage' => '70.00'])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.requirements.final_exam.status', 'passed');
    }

    public function test_required_assignment_must_be_graded_and_passed(): void
    {
        [$student, $course, $enrollment] = $this->courseWithAccess();
        $required = Assignment::factory()->published()->for($course)->create();
        Assignment::factory()->published()->for($course)->create();
        $course->forceFill(['certificate_required_lesson_percentage' => '0.00', 'certificate_requirements_version' => 1])->save();
        $course->requiredCertificateAssignments()->sync([$required->id]);
        Sanctum::actingAs($student);
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['required_assignments_incomplete']);
        $submission = AssignmentSubmission::factory()->for($required)->for($enrollment)->for($student, 'user')->create();
        $submission->forceFill(['status' => 'graded', 'passed' => false, 'score' => '50.00'])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.eligible', false);
        $submission->forceFill(['passed' => true, 'score' => '80.00'])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.requirements.assignments.completed_count', 1);
    }

    public function test_approval_is_separate_from_academic_eligibility_and_issuance(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$student, $course] = $this->courseWithAccess();
        $course->forceFill(['certificate_required_lesson_percentage' => '0.00', 'certificate_requirements_version' => 1, 'certificate_admin_approval_required' => true])->save();
        Sanctum::actingAs($student);
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.academic_eligible', true)
            ->assertJsonPath('data.eligible', false)
            ->assertJsonPath('data.requirements.approval.status', 'not_requested');
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertUnprocessable();
        $id = $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")
            ->assertOk()->assertJsonPath('data.status', 'pending')->json('data.id');
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")->assertJsonPath('data.id', $id);
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertUnprocessable();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/certificate-approval-requests/{$id}/approve")
            ->assertOk()->assertJsonPath('data.approved_by', $admin->id);
        $this->postJson("/api/v1/admin/certificate-approval-requests/{$id}/approve")->assertUnprocessable();
        Sanctum::actingAs($student);
        $certificateId = $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertCreated()->json('data.id');
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertJsonPath('data.id', $certificateId);
        $this->assertDatabaseCount('certificates', 1);

        $course->forceFill(['certificate_required_lesson_percentage' => '50.00', 'certificate_requirements_version' => 2])->save();
        $this->assertDatabaseHas('certificates', ['id' => $certificateId, 'status' => 'issued']);
        $this->getJson($this->eligibilityUrl($course))->assertJsonPath('data.requirements.approval.status', 'not_requested');
    }

    public function test_management_rejects_cross_course_selections_and_instructor_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [, $course] = $this->courseWithAccess();
        $other = Course::factory()->create();
        $quiz = Quiz::factory()->for($other)->create();
        $assignment = Assignment::factory()->for($other)->create();
        $payload = [
            'certificate_enabled' => true,
            'required_lesson_percentage' => '80.00',
            'final_exam_required' => true,
            'final_exam_quiz_id' => $quiz->id,
            'final_exam_passing_percentage' => '70.00',
            'required_assignment_ids' => [$assignment->id],
            'admin_approval_required' => true,
        ];
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/admin/courses/{$course->id}/certificate-requirements", $payload)
            ->assertUnprocessable()->assertJsonValidationErrors(['final_exam_quiz_id', 'required_assignment_ids.0']);

        $payload['final_exam_quiz_id'] = Quiz::factory()->published()->for($course)->create()->id;
        $payload['required_assignment_ids'] = [Assignment::factory()->published()->for($course)->create()->id];
        $this->putJson("/api/v1/admin/courses/{$course->id}/certificate-requirements", $payload)
            ->assertOk()->assertJsonPath('data.requirements_version', 1);
        $this->putJson("/api/v1/admin/courses/{$course->id}/certificate-requirements", $payload)
            ->assertJsonPath('data.requirements_version', 1);

        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        Sanctum::actingAs($instructor);
        $this->getJson("/api/v1/admin/courses/{$course->id}/certificate-requirements")->assertForbidden();
        $this->putJson("/api/v1/admin/courses/{$course->id}/certificate-requirements", $payload)->assertForbidden();
    }

    public function test_suspended_or_expired_access_cannot_request_approval_or_issue(): void
    {
        [$student, $course, $enrollment] = $this->courseWithAccess();
        $course->forceFill(['certificate_required_lesson_percentage' => '0.00', 'certificate_requirements_version' => 1, 'certificate_admin_approval_required' => true])->save();
        Sanctum::actingAs($student);
        $enrollment->forceFill(['status' => 'suspended'])->save();
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['enrollment_suspended']);
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")->assertUnprocessable();
        $enrollment->forceFill(['status' => 'active'])->save();
        $enrollment->accessGrants()->delete();
        $this->getJson($this->eligibilityUrl($course))->assertJsonFragment(['effective_access_missing']);
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")->assertUnprocessable();
    }

    public function test_student_cannot_use_backoffice_endpoints_even_with_shared_catalog_view_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/admin/dashboard-summary')->assertForbidden();
        $this->getJson('/api/v1/admin/courses')->assertForbidden();
        $this->getJson('/api/v1/admin/categories')->assertForbidden();
    }

    public function test_settings_change_invalidates_approval_and_issuance_until_new_request_is_approved(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$student, $course] = $this->courseWithAccess();
        $course->forceFill(['certificate_required_lesson_percentage' => '0.00', 'certificate_requirements_version' => 1, 'certificate_admin_approval_required' => true])->save();
        Sanctum::actingAs($student);
        $id = $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")->json('data.id');
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/certificate-approval-requests/{$id}/approve")->assertOk();
        $course->forceFill(['certificate_requirements_version' => 2])->save();
        Sanctum::actingAs($student);
        $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertUnprocessable();
        $newId = $this->postJson("/api/v1/me/courses/{$course->slug}/certificate-approval-request")->json('data.id');
        $this->assertNotSame($id, $newId);
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/certificate-approval-requests/{$id}/approve")->assertUnprocessable();
        $this->postJson("/api/v1/admin/certificate-approval-requests/{$newId}/approve")->assertOk();
    }

    /** @return array{User, Course, Enrollment, array<int, Lesson>} */
    private function courseWithAccess(): array
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->published()->for($section, 'section')->create()->all();
        $enrollment = Enrollment::factory()->for($student)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        return [$student, $course, $enrollment, $lessons];
    }

    private function eligibilityUrl(Course $course): string
    {
        return "/api/v1/me/courses/{$course->slug}/certificate-eligibility";
    }
}
