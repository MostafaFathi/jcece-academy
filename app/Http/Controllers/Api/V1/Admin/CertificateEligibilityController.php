<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\CertificateEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CertificateEligibilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(User $user, Course $course, CertificateEligibilityService $eligibility): JsonResponse
    {
        Gate::authorize('issue', Certificate::class);
        $result = $eligibility->evaluate($user, $course);

        return response()->json(['data' => [
            'eligible' => $result['eligible'],
            'academic_eligible' => $result['academic_eligible'],
            'reasons' => $result['reasons'],
            'progress' => $result['progress'],
            'requirements' => $result['requirements'],
        ]]);
    }
}
