<?php

namespace App\Http\Controllers\Api\V1\Admin;

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
        Gate::authorize('viewAny', Certificate::class);
        $certificates = Certificate::query()
            ->with('revokedBy')
            ->when($request->integer('user_id'), fn ($query, int $userId) => $query->where('user_id', $userId))
            ->when($request->integer('course_id'), fn ($query, int $courseId) => $query->where('course_id', $courseId))
            ->when($request->string('status')->isNotEmpty(), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('issued_at')
            ->paginate();

        return CertificateResource::collection($certificates);
    }

    public function show(Certificate $certificate): CertificateResource
    {
        Gate::authorize('view', $certificate);

        return new CertificateResource($certificate->load('revokedBy'));
    }
}
