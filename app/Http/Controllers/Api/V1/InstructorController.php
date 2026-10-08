<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicInstructorResource;
use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class InstructorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100']]);

        return PublicInstructorResource::collection($this->visible()
            ->with('instructorProfile')
            ->withCount(['instructedCourses' => fn (Builder $query) => $this->publishedCourses($query)])
            ->orderBy('name')->orderBy('id')->paginate($request->integer('per_page', 12)));
    }

    public function show(User $instructor): PublicInstructorResource
    {
        $visible = $this->visible()->whereKey($instructor->id)->firstOrFail();
        $visible->load([
            'instructorProfile',
            'instructedCourses' => fn (HasMany $query) => $this->publishedCourses($query)->withPublicRatingSummary()->with(['category', 'instructor'])->latest('published_at')->limit(12),
        ]);
        $visible->loadCount(['instructedCourses' => fn (Builder $query) => $this->publishedCourses($query)]);

        return new PublicInstructorResource($visible);
    }

    private function visible(): Builder
    {
        return User::query()->role(RoleName::Instructor->value)
            ->where('status', UserStatus::Active)
            ->whereHas('instructorProfile')
            ->whereHas('instructedCourses', fn (Builder $query) => $this->publishedCourses($query));
    }

    private function publishedCourses(Builder|HasMany $query): Builder|HasMany
    {
        return $query->where('status', CourseStatus::Published)
            ->whereNotNull('published_at')->where('published_at', '<=', now());
    }
}
