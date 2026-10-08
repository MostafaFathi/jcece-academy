<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Services\CertificateEligibilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateEligibilityController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Course $course, CertificateEligibilityService $eligibility): JsonResponse
    {
        $result = $eligibility->evaluate($request->user(), $course);
        $certificateStatus = Certificate::query()
            ->whereBelongsTo($request->user())
            ->whereBelongsTo($course)
            ->latest('issued_at')
            ->value('status');

        return response()->json(['data' => [
            'eligible' => $result['eligible'],
            'academic_eligible' => $result['academic_eligible'],
            'certificate_status' => $certificateStatus,
            'reasons' => $result['reasons'],
            'progress' => $result['progress'],
            'requirements' => $result['requirements'],
        ]]);
    }
}
