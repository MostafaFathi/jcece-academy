<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Course;
use App\Services\CertificateIssuanceService;
use Illuminate\Http\Request;

class CertificateIssuanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Course $course, CertificateIssuanceService $issuance): CertificateResource
    {
        return new CertificateResource($issuance->issue($request->user(), $course));
    }
}
