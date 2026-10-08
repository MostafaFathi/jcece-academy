<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CertificateStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Certificate;
use App\Services\CertificateIssuanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CertificateReissuanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Certificate $certificate, CertificateIssuanceService $issuance): CertificateResource
    {
        Gate::authorize('issue', Certificate::class);
        abort_unless($certificate->status === CertificateStatus::Revoked, 422, 'Only a revoked certificate can be reissued.');
        $certificate->load(['user', 'course']);

        return new CertificateResource($issuance->issue($certificate->user, $certificate->course, true, $request->user()));
    }
}
