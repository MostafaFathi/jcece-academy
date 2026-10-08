<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\CertificateApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateApprovalRequestController extends Controller
{
    public function __invoke(Request $request, Course $course, CertificateApprovalService $approvals): JsonResponse
    {
        $approval = $approvals->request($request->user(), $course);

        return response()->json(['data' => [
            'id' => $approval->id,
            'course_id' => $approval->course_id,
            'status' => $approval->status,
            'requested_at' => $approval->requested_at,
        ]]);
    }
}
