<?php

namespace App\Services;

use App\Models\CertificateApprovalRequest;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CertificateApprovalService
{
    public function __construct(public CertificateEligibilityService $eligibility, private AuditTrail $audit, private TransactionalDeliveryService $deliveries) {}

    public function request(User $student, Course $course): CertificateApprovalRequest
    {
        return DB::transaction(function () use ($student, $course): CertificateApprovalRequest {
            $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();

            if (! $course->certificate_admin_approval_required) {
                throw ValidationException::withMessages(['course' => 'This course does not require certificate approval.']);
            }

            if (! $this->eligibility->evaluate($student, $course)['academic_eligible']) {
                throw ValidationException::withMessages(['course' => 'Academic certificate requirements are not satisfied.']);
            }

            $approvalRequest = CertificateApprovalRequest::query()->firstOrCreate(
                ['course_id' => $course->id, 'user_id' => $student->id, 'requirements_version' => $course->certificate_requirements_version],
                ['status' => CertificateApprovalRequest::Pending, 'requested_at' => now()],
            );

            if ($approvalRequest->wasRecentlyCreated) {
                $this->audit->record('certificate.approval_requested', $approvalRequest, $student);
            }

            return $approvalRequest;
        });
    }

    public function approve(CertificateApprovalRequest $request, User $admin): CertificateApprovalRequest
    {
        return DB::transaction(function () use ($request, $admin): CertificateApprovalRequest {
            $course = Course::query()->whereKey($request->course_id)->lockForUpdate()->firstOrFail();
            $request = CertificateApprovalRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== CertificateApprovalRequest::Pending
                || ! $course->certificate_admin_approval_required
                || $request->requirements_version !== $course->certificate_requirements_version
                || ! $this->eligibility->evaluate($request->user, $course)['academic_eligible']) {
                throw ValidationException::withMessages(['approval' => 'This approval request is no longer eligible or is already approved.']);
            }

            $request->forceFill([
                'status' => CertificateApprovalRequest::Approved,
                'approved_by' => $admin->id,
                'approver_name_snapshot' => $admin->name,
                'approved_at' => now(),
            ])->save();

            $this->audit->record('certificate.approved', $request, $admin);
            $this->deliveries->recordForUser('certificate_approval_granted', 'CertificateApprovalRequest', $request->id, $request->user, ['course_title' => $course->title]);

            return $request->refresh();
        });
    }
}
