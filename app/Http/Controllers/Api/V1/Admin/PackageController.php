<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePackageRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePackageRequest;
use App\Http\Requests\Api\V1\ListPackagesRequest;
use App\Http\Resources\Api\V1\PackageResource;
use App\Models\Package;
use App\PackageStatus;
use App\Services\PackageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListPackagesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Package::class);

        $filters = $request->validated();
        $packages = Package::query()
            ->withCount('courseMemberships')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type));

        match ($filters['sort'] ?? 'latest') {
            'oldest' => $packages->oldest()->orderBy('id'),
            'price_asc' => $packages->orderBy('price')->orderBy('id'),
            'price_desc' => $packages->orderByDesc('price')->orderByDesc('id'),
            'title' => $packages->orderBy('title')->orderBy('id'),
            default => $packages->latest()->orderByDesc('id'),
        };

        return PackageResource::collection($packages->paginate($request->integer('per_page', 25))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePackageRequest $request, PackageService $packageService): JsonResponse
    {
        Gate::authorize('create', Package::class);
        $attributes = $request->validated();

        if (($attributes['status'] ?? PackageStatus::Draft->value) === PackageStatus::Published->value) {
            Gate::authorize('publish', new Package);
        }

        $package = $packageService->create($attributes);

        return (new PackageResource($package))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Package $package): PackageResource
    {
        Gate::authorize('view', $package);

        return new PackageResource($package->load('courseMemberships.course')->loadCount('courseMemberships'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePackageRequest $request, Package $package, PackageService $packageService): PackageResource
    {
        Gate::authorize('update', $package);
        $attributes = $request->validated();

        if (($attributes['status'] ?? null) === PackageStatus::Published->value && $package->status !== PackageStatus::Published) {
            Gate::authorize('publish', $package);
        }

        return new PackageResource($packageService->update($package, $attributes));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Package $package): Response
    {
        Gate::authorize('delete', $package);

        $package->delete();

        return response()->noContent();
    }
}
