<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListUsersRequest;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\Api\V1\AdminUserResource;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Services\AuditTrail;
use App\Services\RolePermissionManagementService;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListUsersRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);
        $filters = $request->validated();
        $users = User::query()->with('roles')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()->orderByDesc('id');

        return AdminUserResource::collection($users->paginate($request->integer('per_page', 25))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUserRequest $request, AuditTrail $audit): JsonResponse
    {
        Gate::authorize('create', User::class);
        $attributes = $request->validated();
        if ($attributes['roles'] !== [RoleName::Student->value]) {
            abort_unless($request->user()->hasRole(RoleName::Admin->value) && $request->user()->can(PermissionName::RolesManage->value), 403);
        }
        $user = DB::transaction(function () use ($attributes, $request, $audit): User {
            $user = User::create([...Arr::only($attributes, ['name', 'email', 'password', 'status']), 'status' => $attributes['status'] ?? UserStatus::Active->value]);
            $user->syncRoles($attributes['roles']);
            foreach ($attributes['roles'] as $roleName) {
                $audit->record('user.role_added', $user, $request->user(), ['role' => $roleName]);
            }
            $audit->record('user.created', $user, $request->user(), ['changed_fields' => 'roles,status']);

            return $user;
        });

        return (new AdminUserResource($user->load('roles')))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): AdminUserResource
    {
        Gate::authorize('view', $user);

        return new AdminUserResource($user->load('roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateUserRequest $request, User $user, AuditTrail $audit, RolePermissionManagementService $roleManagement): AdminUserResource
    {
        Gate::authorize('update', $user);
        $attributes = $request->validated();

        if (array_key_exists('roles', $attributes) || array_key_exists('status', $attributes) || $request->filled('password')) {
            Gate::authorize('manageSensitive', User::class);
        }
        if (array_key_exists('roles', $attributes)) {
            abort_unless($request->user()->hasRole(RoleName::Admin->value) && $request->user()->can(PermissionName::RolesManage->value), 403);
        }

        if (! $request->filled('password')) {
            unset($attributes['password']);
        }

        DB::transaction(function () use ($user, $attributes, $request, $audit, $roleManagement): void {
            $sensitiveAccessChange = array_key_exists('roles', $attributes) || array_key_exists('status', $attributes);
            if ($sensitiveAccessChange) {
                Role::query()->where('name', RoleName::Admin->value)->where('guard_name', 'web')->lockForUpdate()->firstOrFail();
            }
            $beforeRoles = $user->getRoleNames()->all();
            $user->update(Arr::only($attributes, ['name', 'email', 'password', 'status']));
            if (array_key_exists('roles', $attributes)) {
                $user->syncRoles($attributes['roles']);
                foreach (array_diff($attributes['roles'], $beforeRoles) as $roleName) {
                    $audit->record('user.role_added', $user, $request->user(), ['role' => $roleName]);
                }
                foreach (array_diff($beforeRoles, $attributes['roles']) as $roleName) {
                    $audit->record('user.role_removed', $user, $request->user(), ['role' => $roleName]);
                }
            }
            if ($sensitiveAccessChange) {
                $roleManagement->assertActiveAdminManagerExists();
            }
            if (array_intersect(['roles', 'status', 'password'], array_keys($attributes)) !== []) {
                $audit->record('user.privileges_changed', $user, $request->user(), ['changed_fields' => implode(',', array_intersect(['roles', 'status', 'password'], array_keys($attributes)))]);
            }
        });

        return new AdminUserResource($user->refresh()->load('roles'));
    }
}
