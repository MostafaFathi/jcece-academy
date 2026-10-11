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
use App\Services\UploadedFileStorage;
use App\UserStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Throwable;

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
    public function store(StoreInstructorRequest $request, UploadedFileStorage $uploads): JsonResponse
    {
        Gate::authorize('create', InstructorProfile::class);
        $attributes = $request->validated();
        $avatarPath = $request->hasFile('avatar') ? $uploads->store($request->file('avatar'), 'avatars', 'public', 'avatar') : null;

        try {
            $instructor = DB::transaction(function () use ($attributes, $avatarPath): User {
                $instructor = User::create([
                    ...Arr::only($attributes, ['name', 'email', 'password', 'status']),
                    'status' => $attributes['status'] ?? UserStatus::Active->value,
                    'avatar' => $avatarPath === null ? null : Storage::disk('public')->url($avatarPath),
                ]);
                $instructor->assignRole(RoleName::Instructor->value);
                $instructor->instructorProfile()->create(Arr::only($attributes, $this->profileFields()));

                return $instructor;
            });
        } catch (Throwable $exception) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }
            throw $exception;
        }

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
    public function update(UpdateInstructorRequest $request, User $instructor, RolePermissionManagementService $roleManagement, UploadedFileStorage $uploads): AdminInstructorResource
    {
        Gate::authorize('updateAny', InstructorProfile::class);
        abort_unless($instructor->hasRole(RoleName::Instructor->value), 404);
        $attributes = $request->validated();
        $accountAttributes = Arr::only($attributes, ['name', 'email', 'status', 'password']);
        $changesAvatar = $request->hasFile('avatar') || $request->boolean('remove_avatar');
        if (($accountAttributes['password'] ?? null) === null || $accountAttributes['password'] === '') {
            unset($accountAttributes['password']);
        }

        if ($accountAttributes !== [] || $changesAvatar) {
            Gate::authorize('manageAccount', InstructorProfile::class);
            if ($instructor->hasRole(RoleName::Admin->value)) {
                abort_unless($request->user()->hasRole(RoleName::Admin->value), 403);
            }
        }

        $oldAvatar = $instructor->avatar;
        $avatarPath = $request->hasFile('avatar') ? $uploads->store($request->file('avatar'), 'avatars', 'public', 'avatar') : null;
        if ($avatarPath !== null) {
            $accountAttributes['avatar'] = Storage::disk('public')->url($avatarPath);
        } elseif ($request->boolean('remove_avatar')) {
            $accountAttributes['avatar'] = null;
        }

        try {
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
        } catch (Throwable $exception) {
            if ($avatarPath !== null) {
                Storage::disk('public')->delete($avatarPath);
            }
            throw $exception;
        }

        if ($changesAvatar) {
            $this->deleteManagedAvatar($oldAvatar);
        }

        return new AdminInstructorResource($instructor->refresh()->load('instructorProfile'));
    }

    /** @return array<int, string> */
    private function profileFields(): array
    {
        return ['job_title', 'short_bio', 'bio', 'years_experience', 'specialties', 'linkedin_url', 'facebook_url', 'instagram_url', 'website_url', 'is_featured'];
    }

    private function deleteManagedAvatar(?string $url): void
    {
        $path = parse_url($url ?? '', PHP_URL_PATH);
        if (is_string($path) && preg_match('~^/storage/(avatars/[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp))$~', $path, $matches)) {
            Storage::disk('public')->delete($matches[1]);
        }
    }
}
