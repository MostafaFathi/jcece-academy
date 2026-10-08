<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListInstructorsRequest;
use App\Http\Requests\Api\V1\Admin\StoreInstructorRequest;
use App\Http\Requests\Api\V1\Admin\UpdateInstructorRequest;
use App\Http\Resources\Api\V1\AdminInstructorResource;
use App\Models\InstructorProfile;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Services\RolePermissionManagementService;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class InstructorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListInstructorsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InstructorProfile::class);
        $filters = $request->validated();
        $canSearchEmail = $request->user()->can(PermissionName::InstructorsManage->value);
        $instructors = User::query()->role(RoleName::Instructor->value)->with('instructorProfile')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search, $canSearchEmail): void {
                $query->where('name', 'like', "%{$search}%");
                if ($canSearchEmail) {
                    $query->orWhere('email', 'like', "%{$search}%");
                }
            }))
            ->latest()->orderByDesc('id');

        return AdminInstructorResource::collection($instructors->paginate($request->integer('per_page', 25))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreInstructorRequest $request): JsonResponse
    {
        Gate::authorize('create', InstructorProfile::class);
        $attributes = $request->validated();
        $instructor = DB::transaction(function () use ($attributes): User {
            $instructor = User::create([...Arr::only($attributes, ['name', 'email', 'password', 'status']), 'status' => $attributes['status'] ?? UserStatus::Active->value]);
            $instructor->assignRole(RoleName::Instructor->value);
            $instructor->instructorProfile()->create(Arr::only($attributes, $this->profileFields()));

            return $instructor;
        });

        return (new AdminInstructorResource($instructor->load('instructorProfile')))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $instructor): AdminInstructorResource
    {
        Gate::authorize('viewAny', InstructorProfile::class);
        abort_unless($instructor->hasRole(RoleName::Instructor->value), 404);

        return new AdminInstructorResource($instructor->load('instructorProfile'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateInstructorRequest $request, User $instructor, RolePermissionManagementService $roleManagement): AdminInstructorResource
    {
        Gate::authorize('updateAny', InstructorProfile::class);
        abort_unless($instructor->hasRole(RoleName::Instructor->value), 404);
        $attributes = $request->validated();
        $accountAttributes = Arr::only($attributes, ['name', 'email', 'status', 'password']);
        if (($accountAttributes['password'] ?? null) === null || $accountAttributes['password'] === '') {
            unset($accountAttributes['password']);
        }

        if ($accountAttributes !== []) {
            Gate::authorize('manageAccount', InstructorProfile::class);
            if ($instructor->hasRole(RoleName::Admin->value)) {
                abort_unless($request->user()->hasRole(RoleName::Admin->value), 403);
            }
        }

        DB::transaction(function () use ($instructor, $attributes, $accountAttributes, $roleManagement): void {
            $changesManagerStatus = array_key_exists('status', $accountAttributes) && $instructor->hasRole(RoleName::Admin->value);
            if ($changesManagerStatus) {
                Role::query()->where('name', RoleName::Admin->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            }
            if ($accountAttributes !== []) {
                $instructor->update($accountAttributes);
            }
            if ($changesManagerStatus) {
                $roleManagement->assertActiveAdminManagerExists();
            }
            $profileAttributes = Arr::only($attributes, $this->profileFields());
            if ($profileAttributes !== []) {
                $instructor->instructorProfile()->updateOrCreate([], $profileAttributes);
            }
        });

        return new AdminInstructorResource($instructor->refresh()->load('instructorProfile'));
    }

    /** @return array<int, string> */
    private function profileFields(): array
    {
        return ['job_title', 'short_bio', 'bio', 'years_experience', 'specialties', 'linkedin_url', 'facebook_url', 'instagram_url', 'website_url', 'is_featured'];
    }
}
