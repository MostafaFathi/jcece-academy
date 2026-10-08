<?php

namespace App\Http\Resources\Api\V1;

use App\PermissionName;
use App\RoleName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminUserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status->value,
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()),
            'permission_sources' => $this->when($request->routeIs('api.v1.admin.users.show') && $request->user()?->hasRole(RoleName::Admin->value) && $request->user()->can(PermissionName::RolesManage->value), fn (): array => [
                'inherited' => $this->getPermissionsViaRoles()->pluck('name')->sort()->values()->all(),
                'direct' => $this->permissions()->pluck('name')->sort()->values()->all(),
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
