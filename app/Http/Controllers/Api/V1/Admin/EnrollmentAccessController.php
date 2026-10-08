<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\GrantCourseAccessRequest;
use App\Http\Requests\Api\V1\Admin\RevokeCourseAccessGrantRequest;
use App\Http\Resources\Api\V1\EnrollmentAccessGrantResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use App\Services\AuditTrail;
use App\Services\CourseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EnrollmentAccessController extends Controller
{
    public function store(
        GrantCourseAccessRequest $request,
        User $user,
        Course $course,
        CourseAccessService $courseAccessService,
        AuditTrail $audit,
    ): JsonResponse {
        Gate::authorize('manage', Enrollment::class);

        $grant = DB::transaction(function () use ($request, $user, $course, $courseAccessService, $audit): EnrollmentAccessGrant {
            $grant = $courseAccessService->grantAdminAccess(
                $user,
                $course,
                $request->date('access_starts_at') ?? now(),
                $request->exists('access_expires_at'),
                $request->date('access_expires_at'),
            );
            $audit->record('access.admin_granted', $grant, $request->user());

            return $grant;
        });

        return (new EnrollmentAccessGrantResource($grant))->response()->setStatusCode(201);
    }

    public function destroy(
        RevokeCourseAccessGrantRequest $request,
        EnrollmentAccessGrant $grant,
        CourseAccessService $courseAccessService,
        AuditTrail $audit,
    ): EnrollmentAccessGrantResource {
        Gate::authorize('manage', Enrollment::class);

        $grant = DB::transaction(function () use ($request, $grant, $courseAccessService, $audit): EnrollmentAccessGrant {
            $wasRevoked = $grant->revoked_at !== null;
            $grant = $courseAccessService->revokeGrant(
                $grant,
                $request->user(),
                $request->validated('revocation_reason'),
            );

            if (! $wasRevoked) {
                $audit->record('access.revoked', $grant, $request->user());
            }

            return $grant;
        });

        return new EnrollmentAccessGrantResource($grant);
    }
}
