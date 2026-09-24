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
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /**
     * purchase_entitlement_key is the database-enforced identity for retries.
     * source_id remains the corresponding purchased Order Item ID.
     *
     * @return array{grant: EnrollmentAccessGrant, created: bool}
     */
    public function grantPurchasedAccess(
        User $user,
        Course $course,
        AccessGrantSource $source,
        int $sourceId,
        string $entitlementKey,
        CarbonInterface $accessStartsAt,
        ?CarbonInterface $accessExpiresAt,
    ): array {
        if (! in_array($source, [AccessGrantSource::DirectPurchase, AccessGrantSource::PackagePurchase], true)) {
            throw new \InvalidArgumentException('A purchase access grant requires a purchase source.');
        }

        return DB::transaction(function () use ($user, $course, $source, $sourceId, $entitlementKey, $accessStartsAt, $accessExpiresAt): array {
            $timestamp = Date::now();

            DB::table('enrollments')->insertOrIgnore([
                'user_id' => $user->id,
                'course_id' => $course->id,
                'status' => EnrollmentStatus::Active->value,
                'enrolled_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $enrollment = Enrollment::query()
                ->whereBelongsTo($user)
                ->whereBelongsTo($course)
                ->lockForUpdate()
                ->first();

            if ($enrollment === null) {
                throw ValidationException::withMessages([
                    'order' => 'The purchased course enrollment could not be created.',
                ]);
            }

            $created = DB::table('enrollment_access_grants')->insertOrIgnore([
                'enrollment_id' => $enrollment->id,
                'source_type' => $source->value,
                'source_id' => $sourceId,
                'purchase_entitlement_key' => $entitlementKey,
                'access_starts_at' => $accessStartsAt,
                'access_expires_at' => $accessExpiresAt,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]) === 1;

            $grant = EnrollmentAccessGrant::query()
                ->where('purchase_entitlement_key', $entitlementKey)
                ->lockForUpdate()
                ->first();

            if (
                $grant === null
                || $grant->enrollment_id !== $enrollment->id
                || $grant->source_type !== $source
                || $grant->source_id !== $sourceId
            ) {
                throw ValidationException::withMessages([
                    'order' => 'The purchase entitlement conflicts with an existing access grant.',
                ]);
            }

            return ['grant' => $grant, 'created' => $created];
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
