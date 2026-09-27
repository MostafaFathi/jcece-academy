<?php

namespace Tests\Feature;

use App\AssignmentStatus;
use App\CourseStatus;
use App\EnrollmentStatus;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Quiz;
use App\Models\User;
use App\QuizStatus;
use App\Services\CertificateEligibilityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CertificateEligibilityServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_completed_published_lessons_with_effective_access_are_eligible(): void
    {
        [$user, $course] = $this->eligibleCourse();

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertTrue($result['eligible']);
        $this->assertSame([], $result['reasons']);
        $this->assertSame(2, $result['progress']['completed_lessons']);
        $this->assertSame(2, $result['progress']['total_lessons']);
    }

    public function test_empty_course_is_not_eligible(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertFalse($result['eligible']);
        $this->assertContains('course_has_no_applicable_lessons', $result['reasons']);
    }

    public function test_incomplete_published_lesson_prevents_eligibility(): void
    {
        [$user, $course, $enrollment] = $this->eligibleCourse();
        $enrollment->lessonProgress()->firstOrFail()->delete();

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertFalse($result['eligible']);
        $this->assertContains('lessons_incomplete', $result['reasons']);
    }

    public function test_suspended_enrollment_prevents_eligibility(): void
    {
        [$user, $course, $enrollment] = $this->eligibleCourse();
        $enrollment->update(['status' => EnrollmentStatus::Suspended]);

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertFalse($result['eligible']);
        $this->assertContains('enrollment_suspended', $result['reasons']);
        $this->assertContains('effective_access_missing', $result['reasons']);
    }

    public function test_missing_or_expired_access_prevents_eligibility(): void
    {
        [$user, $course, $enrollment] = $this->eligibleCourse();
        $enrollment->accessGrants()->delete();
        EnrollmentAccessGrant::factory()->for($enrollment)->expired()->create();

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertFalse($result['eligible']);
        $this->assertContains('effective_access_missing', $result['reasons']);
    }

    public function test_quizzes_and_assignments_are_not_certificate_requirements(): void
    {
        [$user, $course] = $this->eligibleCourse();
        Quiz::factory()->for($course)->create(['status' => QuizStatus::Published]);
        Assignment::factory()->for($course)->create(['status' => AssignmentStatus::Published]);

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertTrue($result['eligible']);
        $this->assertDatabaseCount('quiz_attempts', 0);
        $this->assertDatabaseCount('assignment_submissions', 0);
    }

    public function test_disabled_and_archived_courses_prevent_new_eligibility(): void
    {
        [$user, $course] = $this->eligibleCourse();
        $course->update(['certificate_enabled' => false, 'status' => CourseStatus::Archived]);

        $result = app(CertificateEligibilityService::class)->evaluate($user, $course);

        $this->assertFalse($result['eligible']);
        $this->assertContains('certificates_disabled', $result['reasons']);
        $this->assertContains('course_archived', $result['reasons']);
    }

    /** @return array{User, Course, Enrollment} */
    private function eligibleCourse(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
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
