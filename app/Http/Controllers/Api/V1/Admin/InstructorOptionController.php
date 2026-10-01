<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListInstructorsRequest;
use App\Http\Resources\Api\V1\InstructorOptionResource;
use App\Models\Course;
use App\Models\User;
use App\RoleName;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class InstructorOptionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ListInstructorsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('selectInstructor', Course::class);
        $search = $request->validated('search');
        $instructors = User::query()->role(RoleName::Instructor->value)
            ->select(['id', 'name'])
            ->with('instructorProfile:id,user_id,job_title')
            ->when($request->user()->hasRole(RoleName::Instructor->value)
                && ! $request->user()->hasAnyRole([RoleName::ContentManager->value, RoleName::Admin->value]),
                fn (Builder $query): Builder => $query->whereKey($request->user()->id))
            ->when($search, fn (Builder $query, string $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')->orderBy('id');

        return InstructorOptionResource::collection($instructors->paginate($request->integer('per_page', 25))->withQueryString());
    }
}
