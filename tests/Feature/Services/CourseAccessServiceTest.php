<?php

namespace Tests\Feature\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\Services\CourseAccessService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseAccessServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_valid_grant_allows_access(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->create([
            'access_starts_at' => now()->subDay(),
            'access_expires_at' => now()->addDay(),
        ]);

        $hasAccess = app(CourseAccessService::class)->hasAccess($user, $course);

        $this->assertTrue($hasAccess);
    }

    #[DataProvider('invalidGrantStates')]
    public function test_invalid_grant_denies_access(string $state): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->{$state}()->create();

        $hasAccess = app(CourseAccessService::class)->hasAccess($user, $course);

        $this->assertFalse($hasAccess);
    }

    public function test_current_lifetime_grant_allows_access_and_reports_lifetime(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [, , $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        $metadata = app(CourseAccessService::class)->accessMetadata($enrollment);

        $this->assertTrue($metadata['has_access']);
        $this->assertTrue($metadata['is_lifetime']);
        $this->assertNull($metadata['access_expires_at']);
    }

    public function test_one_valid_grant_is_enough_when_other_grants_are_invalid(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->expired()->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->revoked()->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->create();

        $hasAccess = app(CourseAccessService::class)->hasAccess($user, $course);

        $this->assertTrue($hasAccess);
    }

    public function test_effective_expiration_uses_latest_current_grant(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [, , $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->create(['access_expires_at' => now()->addDays(10)]);
        EnrollmentAccessGrant::factory()->for($enrollment)->create(['access_expires_at' => now()->addDays(30)]);

        $metadata = app(CourseAccessService::class)->accessMetadata($enrollment);

        $this->assertFalse($metadata['is_lifetime']);
        $this->assertSame('2026-10-31 12:00:00', $metadata['access_expires_at']?->format('Y-m-d H:i:s'));
    }

    public function test_future_lifetime_grant_does_not_make_current_access_lifetime(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [, , $enrollment] = $this->createEnrollment();
        EnrollmentAccessGrant::factory()->for($enrollment)->create(['access_expires_at' => now()->addDays(10)]);
        EnrollmentAccessGrant::factory()->for($enrollment)->future()->lifetime()->create();

        $metadata = app(CourseAccessService::class)->accessMetadata($enrollment);

        $this->assertTrue($metadata['has_access']);
        $this->assertFalse($metadata['is_lifetime']);
        $this->assertSame('2026-10-11 12:00:00', $metadata['access_expires_at']?->format('Y-m-d H:i:s'));
    }

    public function test_suspended_enrollment_denies_access_without_revoking_grants(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $course, $enrollment] = $this->createEnrollment(suspended: true);
        $grant = EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        $metadata = app(CourseAccessService::class)->accessMetadata($enrollment);

        $this->assertFalse(app(CourseAccessService::class)->hasAccess($user, $course));
        $this->assertFalse($metadata['has_access']);
        $this->assertNull($grant->fresh()->revoked_at);
    }

    public function test_completed_enrollment_retains_access_while_a_grant_is_valid(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $enrollment = Enrollment::factory()->completed()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        $hasAccess = app(CourseAccessService::class)->hasAccess($user, $course);

        $this->assertTrue($hasAccess);
    }

    /** @return array<string, array{string}> */
    public static function invalidGrantStates(): array
    {
        return [
            'expired grant' => ['expired'],
            'revoked grant' => ['revoked'],
            'future grant' => ['future'],
        ];
    }

    /** @return array{User, Course, Enrollment} */
    private function createEnrollment(bool $suspended = false): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $factory = Enrollment::factory()->for($user)->for($course);
        $enrollment = $suspended ? $factory->suspended()->create() : $factory->create();

        return [$user, $course, $enrollment];
    }
}
