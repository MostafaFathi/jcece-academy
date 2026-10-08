<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RevokeCertificateRequest;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Certificate;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CertificateRevocationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RevokeCertificateRequest $request, Certificate $certificate, AuditTrail $audit): CertificateResource
    {
        Gate::authorize('revoke', $certificate);

        $certificate = DB::transaction(function () use ($request, $certificate, $audit): Certificate {
            $lockedCertificate = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);

            if ($lockedCertificate->status === CertificateStatus::Issued) {
                $lockedCertificate->update([
                    'status' => CertificateStatus::Revoked,
                    'active_key' => null,
                    'revoked_at' => now(),
                    'revoked_by' => $request->user()->id,
                    'revocation_reason' => $request->validated('reason'),
                ]);
                $audit->record('certificate.revoked', $lockedCertificate, $request->user());
            }

            return $lockedCertificate->refresh();
        });

        return new CertificateResource($certificate->load('revokedBy'));
    }
}
