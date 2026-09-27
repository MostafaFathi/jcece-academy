<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCertificateVerificationResource;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;

class CertificateVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(string $token): PublicCertificateVerificationResource|JsonResponse
    {
        $certificate = Certificate::query()->where('verification_token', $token)->first();

        if ($certificate === null) {
            return response()->json(['data' => ['status' => 'unknown']], 404);
        }

        return new PublicCertificateVerificationResource($certificate);
    }
}
