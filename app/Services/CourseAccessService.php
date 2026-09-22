<?php

namespace App\Services;

use App\AccessGrantSource;
use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CourseAccessService
{
    public function hasAccess(User $user, Course $course): bool
    {
        $enrollment = $this->enrollmentFor($user, $course);

        return $enrollment !== null && $this->enrollmentHasAccess($enrollment);
    }

    public function enrollmentFor(User $user, Course $course): ?Enrollment
    {
        return Enrollment::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($course)
            ->first();
    }

    public function enrollmentHasAccess(Enrollment $enrollment): bool
    {
        return $enrollment->status !== EnrollmentStatus::Suspended
            && $enrollment->accessGrants()->currentlyValid()->exists();
    }

    /** @return array{has_access: bool, access_expires_at: ?CarbonInterface, is_lifetime: bool} */
    public function accessMetadata(Enrollment $enrollment): array
    {
        if ($enrollment->status === EnrollmentStatus::Suspended) {
            return [
                'has_access' => false,
                'access_expires_at' => null,
                'is_lifetime' => false,
            ];
        }

        $validGrants = $enrollment->relationLoaded('currentAccessGrants')
            ? $enrollment->currentAccessGrants
            : $enrollment->currentAccessGrants()->get();
        $hasAccess = $validGrants->isNotEmpty();
        $isLifetime = $hasAccess && $validGrants->contains(fn (EnrollmentAccessGrant $grant): bool => $grant->access_expires_at === null);

        return [
            'has_access' => $hasAccess,
            'access_expires_at' => $hasAccess && ! $isLifetime ? $validGrants->max('access_expires_at') : null,
            'is_lifetime' => $isLifetime,
        ];
    }

    /** @throws AuthorizationException */
    public function requireAccess(User $user, Course $course): Enrollment
    {
        $enrollment = $this->enrollmentFor($user, $course);

        if ($enrollment === null || ! $this->enrollmentHasAccess($enrollment)) {
            throw new AuthorizationException('You do not currently have access to this course.');
        }

        return $enrollment;
    }

    public function grantAdminAccess(
        User $user,
        Course $course,
        CarbonInterface $accessStartsAt,
        bool $expirationWasProvided,
        ?CarbonInterface $accessExpiresAt = null,
    ): EnrollmentAccessGrant {
        if (! $expirationWasProvided && $course->access_duration_days !== null) {
            $accessExpiresAt = $accessStartsAt->toImmutable()->addDays($course->access_duration_days);
        }

        return $this->grantAccess(
            $user,
            $course,
            AccessGrantSource::Admin,
            $accessStartsAt,
            $accessExpiresAt,
        );
    }

    public function grantAccess(
        User $user,
        Course $course,
        AccessGrantSource $source,
        CarbonInterface $accessStartsAt,
        ?CarbonInterface $accessExpiresAt,
        ?int $sourceId = null,
    ): EnrollmentAccessGrant {
        return DB::transaction(function () use ($user, $course, $source, $accessStartsAt, $accessExpiresAt, $sourceId): EnrollmentAccessGrant {
            $enrollment = Enrollment::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                ['status' => EnrollmentStatus::Active, 'enrolled_at' => now()],
            );

            return $enrollment->accessGrants()->create([
                'source_type' => $source,
                'source_id' => $sourceId,
                'access_starts_at' => $accessStartsAt,
                'access_expires_at' => $accessExpiresAt,
            ]);
        });
    }

    public function revokeGrant(EnrollmentAccessGrant $grant, User $revokedBy, ?string $reason = null): EnrollmentAccessGrant
    {
        return DB::transaction(function () use ($grant, $revokedBy, $reason): EnrollmentAccessGrant {
            $lockedGrant = EnrollmentAccessGrant::query()->lockForUpdate()->findOrFail($grant->id);

            if ($lockedGrant->revoked_at === null) {
                $lockedGrant->update([
                    'revoked_at' => now(),
                    'revoked_by' => $revokedBy->id,
                    'revocation_reason' => $reason,
                ]);
            }

            return $lockedGrant->refresh();
        });
    }
}
