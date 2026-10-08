<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateRolePermissionsRequest;
use App\PermissionName;
use App\RoleName;
use App\Services\AuditTrail;
use App\Services\RolePermissionManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    public function index(Request $request, RolePermissionManagementService $management): JsonResponse
    {
        $this->authorizeManager($request);

        return response()->json([
            'roles' => array_map(fn (RoleName $name): array => $management->snapshot(Role::findByName($name->value, 'web')), RoleName::cases()),
            'permissions' => $management->catalog(),
        ]);
    }

    public function show(Request $request, string $role, RolePermissionManagementService $management): JsonResponse
    {
        $this->authorizeManager($request);

        return response()->json($management->snapshot($this->systemRole($role)));
    }

    public function update(UpdateRolePermissionsRequest $request, string $role, RolePermissionManagementService $management, AuditTrail $audit): JsonResponse
    {
        $systemRole = $this->systemRole($role);
        $validated = $request->validated();

        return response()->json($management->update($systemRole, $validated['permissions'], $validated['version'], $request->user(), $audit));
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->hasRole(RoleName::Admin->value) && $request->user()->can(PermissionName::RolesManage->value), 403);
    }

    private function systemRole(string $name): Role
    {
        abort_unless(RoleName::tryFrom($name), 404);

        return Role::findByName($name, 'web');
    }
}
