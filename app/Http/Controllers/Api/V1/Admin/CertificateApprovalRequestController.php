<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CertificateApprovalRequest;
use App\RoleName;
use App\Services\CertificateApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CertificateApprovalRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);
        $approvals = CertificateApprovalRequest::query()
            ->with(['course:id,title', 'user:id,name,email', 'approver:id,name'])
            ->when($request->integer('course_id'), fn ($query, int $courseId) => $query->where('course_id', $courseId))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('requested_at')
            ->paginate();

        return response()->json($approvals);
    }

    public function approve(Request $request, CertificateApprovalRequest $approvalRequest, CertificateApprovalService $approvals): JsonResponse
    {
        $this->authorizeAdmin($request);
        $approval = $approvals->approve($approvalRequest, $request->user());

        return response()->json(['data' => [
            'id' => $approval->id,
            'course_id' => $approval->course_id,
            'user_id' => $approval->user_id,
            'status' => $approval->status,
            'approved_by' => $approval->approved_by,
            'approved_at' => $approval->approved_at,
        ]]);
    }

    private function authorizeAdmin(Request $request): void
    {
        Gate::authorize('issue', Certificate::class);
        abort_unless($request->user()->hasRole(RoleName::Admin->value), 403);
    }
}
