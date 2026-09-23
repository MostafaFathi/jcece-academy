<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ReorderPackageCoursesRequest;
use App\Http\Resources\Api\V1\PackageCourseResource;
use App\Models\Package;
use App\Services\PackageCourseOrderService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PackageCourseOrderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ReorderPackageCoursesRequest $request,
        Package $package,
        PackageCourseOrderService $orderService,
    ): AnonymousResourceCollection {
        Gate::authorize('update', $package);

        $orderService->reorder($package, $request->validated('ids'));

        return PackageCourseResource::collection($package->courseMemberships()->with('course')->get());
    }
}
