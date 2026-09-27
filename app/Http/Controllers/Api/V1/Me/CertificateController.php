<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CertificateResource;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CertificateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return CertificateResource::collection(
            Certificate::query()->whereBelongsTo($request->user())->latest('issued_at')->paginate(),
        );
    }

    public function show(Request $request, Certificate $certificate): CertificateResource
    {
        abort_unless($certificate->user_id === $request->user()->id, 404);
        Gate::authorize('view', $certificate);

        return new CertificateResource($certificate);
    }
}
