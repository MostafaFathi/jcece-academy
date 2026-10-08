<?php

namespace App\Services;

use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\UserStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionManagementService
{
    /** @return array<int, array{key: string, group: string, sensitive: bool}> */
    public function catalog(): array
    {
        $registered = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $sensitive = [
            PermissionName::RolesManage,
            PermissionName::UsersManage,
            PermissionName::UsersUpdate,
            PermissionName::InstructorsManage,
            PermissionName::EnrollmentsManage,
            PermissionName::OrdersManage,
            PermissionName::PaymentsManage,
            PermissionName::RefundsManage,
            PermissionName::FinancialDocumentsView,
            PermissionName::TransactionalDeliveriesView,
            PermissionName::ReportsView,
            PermissionName::ReportsExport,
            PermissionName::CertificatesIssue,
            PermissionName::CertificatesRevoke,
            PermissionName::TransactionalDeliveriesRetry,
        ];

        return array_map(fn (PermissionName $permission): array => [
            'key' => $permission->value,
            'group' => $this->groupFor($permission->value),
            'sensitive' => in_array($permission, $sensitive, true),
        ], array_values(array_filter(PermissionName::cases(), fn (PermissionName $permission): bool => in_array($permission->value, $registered, true))));
    }

    /** @return array{role: string, permissions: array<int, string>, version: string, unmanaged_count: int} */
    public function snapshot(Role $role): array
    {
        $assigned = $this->assignedKeys($role);
        $managed = $this->managedKeys();

        return [
            'role' => $role->name,
            'permissions' => array_values(array_intersect($assigned, $managed)),
            'version' => hash('sha256', implode('|', $assigned)),
            'unmanaged_count' => count(array_diff($assigned, $managed)),
        ];
    }

    /**
     * @param  array<int, string>  $requestedKeys
     * @return array{role: string, permissions: array<int, string>, version: string, unmanaged_count: int}
     */
    public function update(Role $role, array $requestedKeys, string $version, User $actor, AuditTrail $audit): array
    {
        try {
            return DB::transaction(function () use ($role, $requestedKeys, $version, $actor, $audit): array {
                $lockedRole = Role::query()->whereKey($role->getKey())->lockForUpdate()->firstOrFail();
                $before = $this->assignedKeys($lockedRole);
                if (! hash_equals(hash('sha256', implode('|', $before)), $version)) {
                    abort(409, 'Role permissions changed. Reload and review before saving.');
                }

                $managedKeys = $this->managedKeys();
                $after = array_values(array_unique([...array_diff($before, $managedKeys), ...$requestedKeys]));
                sort($after);
                if ($before === $after) {
                    return $this->snapshot($lockedRole);
                }

                $lockedRole->syncPermissions($after);
                app(PermissionRegistrar::class)->forgetCachedPermissions();
                $this->assertActiveAdminManagerExists();

                $added = array_values(array_diff($after, $before));
                $removed = array_values(array_diff($before, $after));
                foreach ($added as $key) {
                    $audit->record('role.permission_added', $lockedRole, $actor, ['role' => $lockedRole->name, 'permission' => $key]);
                }
                foreach ($removed as $key) {
                    $audit->record('role.permission_removed', $lockedRole, $actor, ['role' => $lockedRole->name, 'permission' => $key]);
                }
                $audit->record('role.permissions_changed', $lockedRole, $actor, [
                    'role' => $lockedRole->name,
                    'before_permissions' => implode(',', $before),
                    'after_permissions' => implode(',', $after),
                    'added_permissions' => implode(',', $added),
                    'removed_permissions' => implode(',', $removed),
                ]);

                return $this->snapshot($lockedRole);
            });
        } finally {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function assertActiveAdminManagerExists(): void
    {
        $exists = User::query()
            ->where('status', UserStatus::Active->value)
            ->role(RoleName::Admin->value)
            ->get()
            ->contains(fn (User $user): bool => $user->can(PermissionName::RolesManage->value));

        if (! $exists) {
            throw ValidationException::withMessages([
                'permissions' => 'At least one active administrator must retain role-management access.',
            ]);
        }
    }

    /** @return array<int, string> */
    private function assignedKeys(Role $role): array
    {
        $keys = $role->permissions()->pluck('name')->all();
        sort($keys);

        return $keys;
    }

    /** @return array<int, string> */
    private function managedKeys(): array
    {
        return array_column(PermissionName::cases(), 'value');
    }

    private function groupFor(string $key): string
    {
        $module = explode('.', $key)[0];

        return match ($module) {
            'users', 'instructors', 'roles' => 'access',
            'courses', 'categories', 'enrollments' => 'courses',
            'curriculum' => 'curriculum',
            'assessments', 'assignments', 'assignment_submissions' => 'assessments',
            'packages' => 'packages',
            'orders', 'payments', 'coupons', 'refunds', 'financial_documents', 'transactional_deliveries' => 'commerce',
            'certificates' => 'certificates',
            'reviews' => 'reviews',
            'support_tickets' => 'support',
            'policy_pages', 'site_content' => 'content',
            'reports' => 'reports',
            default => 'other',
        };
    }
}
