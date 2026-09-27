<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use App\Services\CertificateIssuanceService;
use Illuminate\Support\Facades\Gate;

class CertificateIssuanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(User $user, Course $course, CertificateIssuanceService $issuance): CertificateResource
    {
        Gate::authorize('issue', Certificate::class);

        return new CertificateResource($issuance->issue($user, $course));
    }
}
