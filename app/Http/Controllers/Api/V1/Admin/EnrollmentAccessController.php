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
use App\Services\CourseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EnrollmentAccessController extends Controller
{
    public function store(
        GrantCourseAccessRequest $request,
        User $user,
        Course $course,
        CourseAccessService $courseAccessService,
    ): JsonResponse {
        Gate::authorize('manage', Enrollment::class);

        $grant = $courseAccessService->grantAdminAccess(
            $user,
            $course,
            $request->date('access_starts_at') ?? now(),
            $request->exists('access_expires_at'),
            $request->date('access_expires_at'),
        );

        return (new EnrollmentAccessGrantResource($grant))->response()->setStatusCode(201);
    }

    public function destroy(
        RevokeCourseAccessGrantRequest $request,
        EnrollmentAccessGrant $grant,
        CourseAccessService $courseAccessService,
    ): EnrollmentAccessGrantResource {
        Gate::authorize('manage', Enrollment::class);

        $grant = $courseAccessService->revokeGrant(
            $grant,
            $request->user(),
            $request->validated('revocation_reason'),
        );

        return new EnrollmentAccessGrantResource($grant);
    }
}
