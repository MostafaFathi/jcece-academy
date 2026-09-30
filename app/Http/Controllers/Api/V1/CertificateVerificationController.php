<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCertificateVerificationResource;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CertificateVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, string $token): PublicCertificateVerificationResource|JsonResponse|RedirectResponse
    {
        if (str_contains($request->header('Accept', ''), 'text/html') && ! $request->expectsJson()) {
            return redirect()->route('certificates.verify.page', ['token' => $token]);
        }

        $certificate = Certificate::query()->where('verification_token', $token)->first();

        if ($certificate === null) {
            return response()->json(['data' => ['status' => 'unknown']], 404);
        }

        return new PublicCertificateVerificationResource($certificate);
    }
}
