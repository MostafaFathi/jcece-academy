<?php

namespace Database\Seeders;

use App\PermissionName;
use App\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PermissionName::cases() as $permissionName) {
            Permission::findOrCreate($permissionName->value);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            RoleName::Student->value => [
                PermissionName::CoursesView->value,
                PermissionName::CategoriesView->value,
            ],
            RoleName::Instructor->value => [
                PermissionName::CoursesView->value,
                PermissionName::CoursesCreate->value,
                PermissionName::CoursesUpdate->value,
                PermissionName::CategoriesView->value,
                PermissionName::InstructorsView->value,
            ],
            RoleName::ContentManager->value => [
                PermissionName::CoursesView->value,
                PermissionName::CoursesCreate->value,
                PermissionName::CoursesUpdate->value,
                PermissionName::CoursesDelete->value,
                PermissionName::CoursesPublish->value,
                PermissionName::CategoriesView->value,
                PermissionName::CategoriesCreate->value,
                PermissionName::CategoriesUpdate->value,
                PermissionName::CategoriesDelete->value,
                PermissionName::InstructorsView->value,
                PermissionName::InstructorsUpdate->value,
            ],
            RoleName::SalesSupport->value => [
                PermissionName::CoursesView->value,
                PermissionName::CategoriesView->value,
                PermissionName::InstructorsView->value,
                PermissionName::UsersView->value,
                PermissionName::UsersUpdate->value,
            ],
            RoleName::Admin->value => array_column(PermissionName::cases(), 'value'),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            Role::findOrCreate($roleName)->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
