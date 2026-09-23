<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StorePackageCourseRequest;
use App\Http\Requests\Api\V1\Admin\UpdatePackageCourseRequest;
use App\Http\Resources\Api\V1\PackageCourseResource;
use App\Models\Package;
use App\Models\PackageCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class PackageCourseController extends Controller
{
    public function index(Package $package): AnonymousResourceCollection
    {
        Gate::authorize('view', $package);

        return PackageCourseResource::collection($package->courseMemberships()->with('course')->get());
    }

    public function store(StorePackageCourseRequest $request, Package $package): JsonResponse
    {
        Gate::authorize('update', $package);
        $attributes = $request->validated();
        $maximumSortOrder = $package->courseMemberships()->max('sort_order');
        $attributes['sort_order'] ??= $maximumSortOrder === null ? 0 : ((int) $maximumSortOrder) + 1;

        $membership = $package->courseMemberships()->create($attributes)->load('course');

        return (new PackageCourseResource($membership))->response()->setStatusCode(201);
    }

    public function update(UpdatePackageCourseRequest $request, Package $package, PackageCourse $courseMembership): PackageCourseResource
    {
        Gate::authorize('update', $package);

        $courseMembership->update($request->validated());

        return new PackageCourseResource($courseMembership->refresh()->load('course'));
    }

    public function destroy(Package $package, PackageCourse $courseMembership): Response
    {
        Gate::authorize('update', $package);

        $courseMembership->delete();

        return response()->noContent();
    }
}
