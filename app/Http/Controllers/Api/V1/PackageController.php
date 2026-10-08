<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListPackagesRequest;
use App\Http\Resources\Api\V1\PublicPackageResource;
use App\Models\Package;
use App\PackageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PackageController extends Controller
{
    public function index(ListPackagesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $publishedCourseConstraint = static fn (Builder $query): Builder => $query
            ->where('status', CourseStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
        $packages = Package::query()
            ->where('status', PackageStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->withCount(['courseMemberships' => fn (Builder $query) => $query->whereHas('course', $publishedCourseConstraint)])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            }))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type));

        match ($filters['sort'] ?? 'latest') {
            'oldest' => $packages->oldest('published_at')->orderBy('id'),
            'price_asc' => $packages->orderBy('price')->orderBy('id'),
            'price_desc' => $packages->orderByDesc('price')->orderByDesc('id'),
            'title' => $packages->orderBy('title')->orderBy('id'),
            default => $packages->latest('published_at')->orderByDesc('id'),
        };

        return PublicPackageResource::collection($packages->paginate($request->integer('per_page', 15))->withQueryString());
    }

    public function show(Package $package): PublicPackageResource
    {
        abort_unless(
            $package->status === PackageStatus::Published
            && $package->published_at !== null
            && $package->published_at->isPast(),
            404,
        );

        $publishedCourseConstraint = static fn (Builder $query): Builder => $query
            ->where('status', CourseStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());

        $package->load([
            'courseMemberships' => fn (HasMany $query): HasMany => $query->whereHas('course', $publishedCourseConstraint),
            'courseMemberships.course.instructor',
            'courseMemberships.course.category',
        ])->loadCount([
            'courseMemberships' => fn (Builder $query): Builder => $query->whereHas('course', $publishedCourseConstraint),
            'courseMemberships as total_memberships_count',
        ]);

        return new PublicPackageResource($package);
    }
}
