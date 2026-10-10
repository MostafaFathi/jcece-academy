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

        $newPermissionKeys = [];
        foreach (PermissionName::cases() as $permissionName) {
            $permission = Permission::findOrCreate($permissionName->value, 'web');
            if ($permission->wasRecentlyCreated) {
                $newPermissionKeys[] = $permissionName->value;
            }
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
                PermissionName::CurriculumView->value,
                PermissionName::AssignmentSubmissionsView->value,
                PermissionName::AssignmentSubmissionsGrade->value,
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
                PermissionName::CurriculumView->value,
                PermissionName::CurriculumCreate->value,
                PermissionName::CurriculumUpdate->value,
                PermissionName::CurriculumDelete->value,
                PermissionName::PackagesView->value,
                PermissionName::PackagesCreate->value,
                PermissionName::PackagesUpdate->value,
                PermissionName::PackagesDelete->value,
                PermissionName::PackagesPublish->value,
                PermissionName::AssessmentsView->value,
                PermissionName::AssessmentsCreate->value,
                PermissionName::AssessmentsUpdate->value,
                PermissionName::AssessmentsDelete->value,
                PermissionName::AssessmentsPublish->value,
                PermissionName::AssessmentResultsView->value,
                PermissionName::AssignmentsView->value,
                PermissionName::AssignmentsCreate->value,
                PermissionName::AssignmentsUpdate->value,
                PermissionName::AssignmentsDelete->value,
                PermissionName::AssignmentsPublish->value,
                PermissionName::AssignmentSubmissionsView->value,
                PermissionName::AssignmentSubmissionsGrade->value,
                PermissionName::CertificatesView->value,
                PermissionName::CertificatesIssue->value,
                PermissionName::CertificatesRevoke->value,
                PermissionName::ReviewsView->value,
                PermissionName::ReviewsModerate->value,
                PermissionName::PolicyPagesView->value,
                PermissionName::PolicyPagesUpdate->value,
            ],
            RoleName::SalesSupport->value => [
                PermissionName::CoursesView->value,
                PermissionName::CategoriesView->value,
                PermissionName::InstructorsView->value,
                PermissionName::UsersView->value,
                PermissionName::UsersUpdate->value,
                PermissionName::EnrollmentsView->value,
                PermissionName::EnrollmentsManage->value,
                PermissionName::OrdersView->value,
                PermissionName::OrdersManage->value,
                PermissionName::PaymentsView->value,
                PermissionName::PaymentsManage->value,
                PermissionName::SupportTicketsView->value,
                PermissionName::SupportTicketsManage->value,
                PermissionName::SupportTicketsReply->value,
            ],
            RoleName::Admin->value => array_values(array_filter(array_column(PermissionName::cases(), 'value'), fn (string $key): bool => ! str_starts_with($key, 'messaging.'))),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            if ($role->wasRecentlyCreated) {
                $role->givePermissionTo($permissions);
            } elseif ($roleName === RoleName::Admin->value && in_array(PermissionName::RolesManage->value, $newPermissionKeys, true)) {
                $role->givePermissionTo(PermissionName::RolesManage->value);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
