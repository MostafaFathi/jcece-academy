<?php

namespace Tests\Feature;

use App\CertificateStatus;
use App\Contracts\CertificatePdfGenerator;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CertificateApiTest extends TestCase
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
                return '%PDF-1.4 generated certificate';
            }
        });
    }

    public function test_student_can_check_eligibility_issue_idempotently_list_show_and_download(): void
    {
        [$user, $course] = $this->eligibleCourse(studentName: 'Original Student', courseTitle: 'Original Course');
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/me/courses/{$course->slug}/certificate-eligibility")
            ->assertOk()
            ->assertJsonPath('data.eligible', true)
            ->assertJsonPath('data.progress.progress_percentage', 100);

        $first = $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")
            ->assertCreated()
            ->assertJsonPath('data.student_name', 'Original Student')
            ->assertJsonPath('data.course_title', 'Original Course');
        $certificateId = $first->json('data.id');
        $secondId = $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")
            ->assertOk()
            ->json('data.id');

        $this->assertSame($certificateId, $secondId);
        $this->assertDatabaseCount('certificates', 1);
        $this->getJson('/api/v1/me/certificates')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/me/certificates/{$certificateId}")->assertOk();
        $this->get("/api/v1/me/certificates/{$certificateId}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_student_cannot_access_another_students_certificate_or_pdf(): void
    {
        [$owner, $course] = $this->eligibleCourse();
        Sanctum::actingAs($owner);
        $certificateId = $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->json('data.id');

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/me/certificates/{$certificateId}")->assertNotFound();
        $this->get("/api/v1/me/certificates/{$certificateId}/download")->assertNotFound();
    }

    public function test_snapshots_remain_immutable_after_names_and_curriculum_change(): void
    {
        [$user, $course] = $this->eligibleCourse(studentName: 'Snapshot Student', courseTitle: 'Snapshot Course');
        Sanctum::actingAs($user);
        $certificateId = $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->json('data.id');

        $user->update(['name' => 'Changed Student']);
        $course->update(['title' => 'Changed Course']);
        Lesson::factory()->published()->for($course->sections()->firstOrFail(), 'section')->create();

        $this->getJson("/api/v1/me/certificates/{$certificateId}")
            ->assertOk()
            ->assertJsonPath('data.student_name', 'Snapshot Student')
            ->assertJsonPath('data.course_title', 'Snapshot Course');
    }

    public function test_admin_and_content_manager_can_manage_certificates_but_instructor_cannot(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$student, $course] = $this->eligibleCourse();
        $contentManager = User::factory()->create();
        $contentManager->assignRole(RoleName::ContentManager->value);
        Sanctum::actingAs($contentManager);

        $certificateId = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/certificates")
            ->assertCreated()
            ->json('data.id');
        $this->getJson('/api/v1/admin/certificates')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/admin/certificates/{$certificateId}")->assertOk();

        $instructor = User::factory()->create();
        $instructor->assignRole(RoleName::Instructor->value);
        Sanctum::actingAs($instructor);

        $this->getJson('/api/v1/admin/certificates')->assertForbidden();
        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/certificates")->assertForbidden();
    }

    public function test_revocation_preserves_audit_and_requires_explicit_reissue(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        [$student, $course] = $this->eligibleCourse();
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);
        Sanctum::actingAs($admin);
        $certificateId = $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/certificates")->json('data.id');

        $this->postJson("/api/v1/admin/certificates/{$certificateId}/revoke", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/admin/certificates/{$certificateId}/revoke", ['reason' => 'Issued to the wrong legal name.'])
            ->assertOk()
            ->assertJsonPath('data.status', CertificateStatus::Revoked->value)
            ->assertJsonPath('data.revoked_by.id', $admin->id)
            ->assertJsonPath('data.revocation_reason', 'Issued to the wrong legal name.');
        $this->get("/api/v1/admin/certificates/{$certificateId}/download")->assertOk();
        $this->postJson("/api/v1/admin/users/{$student->id}/courses/{$course->id}/certificates")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('certificate');

        $reissuedId = $this->postJson("/api/v1/admin/certificates/{$certificateId}/reissue")
            ->assertCreated()
            ->assertJsonPath('data.status', CertificateStatus::Issued->value)
            ->json('data.id');

        $this->assertNotSame($certificateId, $reissuedId);
        $this->assertDatabaseCount('certificates', 2);
        $this->assertDatabaseHas('certificates', [
            'id' => $certificateId,
            'status' => CertificateStatus::Revoked->value,
            'revoked_by' => $admin->id,
        ]);
    }

    public function test_public_verification_distinguishes_valid_revoked_and_unknown_without_private_data(): void
    {
        [$user, $course] = $this->eligibleCourse();
        Sanctum::actingAs($user);
        $certificate = Certificate::query()->findOrFail(
            $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->json('data.id'),
        );

        $valid = $this->getJson("/api/v1/certificates/verify/{$certificate->verification_token}")
            ->assertOk()
            ->assertJsonPath('data.status', CertificateStatus::Issued->value)
            ->assertJsonMissingPath('data.user_id')
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.pdf_path')
            ->assertJsonMissingPath('data.verification_token');
        $this->assertSame(
            ['status', 'certificate_number', 'student_name', 'course_title', 'issued_at'],
            array_keys($valid->json('data')),
        );

        $certificate->update(['status' => CertificateStatus::Revoked, 'active_key' => null, 'revoked_at' => now()]);
        $this->getJson("/api/v1/certificates/verify/{$certificate->verification_token}")
            ->assertOk()
            ->assertJsonPath('data.status', CertificateStatus::Revoked->value);
        $this->getJson('/api/v1/certificates/verify/not-a-real-token')
            ->assertNotFound()
            ->assertJsonPath('data.status', 'unknown');
    }

    public function test_database_unique_key_protects_against_concurrent_active_duplicates(): void
    {
        [$user, $course, $enrollment] = $this->eligibleCourse();
        Certificate::factory()->for($user)->for($course)->for($enrollment)->create();

        $this->expectException(QueryException::class);
        Certificate::factory()->for($user)->for($course)->for($enrollment)->create();
    }

    public function test_pdf_failure_leaves_no_certificate_or_file(): void
    {
        [$user, $course] = $this->eligibleCourse();
        $this->app->instance(CertificatePdfGenerator::class, new class implements CertificatePdfGenerator
        {
            public function generate(Certificate $certificate, string $verificationUrl): string
            {
                throw new \RuntimeException('PDF renderer failed.');
            }
        });
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/me/courses/{$course->slug}/certificates")->assertServerError();

        $this->assertDatabaseCount('certificates', 0);
        Storage::disk('local')->assertDirectoryEmpty('certificates');
    }

    /** @return array{User, Course, Enrollment} */
    private function eligibleCourse(string $studentName = 'Eligible Student', string $courseTitle = 'Safety Engineering'): array
    {
        $user = User::factory()->create(['name' => $studentName]);
        $course = Course::factory()->published()->create(['title' => $courseTitle]);
        $section = CourseSection::factory()->for($course)->create();
        $lessons = Lesson::factory()->count(2)->published()->for($section, 'section')->create();
        $enrollment = Enrollment::factory()->completed()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        foreach ($lessons as $lesson) {
            LessonProgress::factory()->completed()->for($enrollment)->for($lesson)->create();
        }

        return [$user, $course, $enrollment];
    }
}
