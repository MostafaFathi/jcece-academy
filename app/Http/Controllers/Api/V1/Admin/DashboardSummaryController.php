<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use App\Services\Reporting\ReportFilters;
use App\Services\Reporting\ReportQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ReportQueryService $reports): JsonResponse
    {
        $actor = $request->user();
        abort_if(
            $actor->hasRole(RoleName::Instructor->value)
                && ! $actor->hasAnyRole([RoleName::Admin->value, RoleName::ContentManager->value]),
            403,
        );
        $canViewCategories = $actor->can(PermissionName::CategoriesView->value);
        $canViewCourses = $actor->can(PermissionName::CoursesView->value);
        $canViewUsers = $actor->can(PermissionName::UsersView->value);
        $canViewInstructors = $actor->can(PermissionName::InstructorsView->value);
        $canViewPackages = $actor->can(PermissionName::PackagesView->value);
        abort_unless($canViewCategories || $canViewCourses || $canViewUsers || $canViewInstructors || $canViewPackages || $actor->can(PermissionName::ReportsView->value), 403);

        $summary = [];
        if ($canViewCategories) {
            $summary['total_categories'] = Category::query()->count();
        }
        if ($canViewCourses) {
            $summary['total_courses'] = Course::query()->count();
            $summary['published_courses'] = Course::query()->where('status', CourseStatus::Published)->count();
            $summary['draft_courses'] = Course::query()->where('status', CourseStatus::Draft)->count();
        }
        if ($canViewUsers) {
            $summary['total_users'] = User::query()->count();
            $summary['total_students'] = User::query()->role(RoleName::Student->value)->count();
        }
        if ($canViewInstructors) {
            $summary['total_instructors'] = User::query()->role(RoleName::Instructor->value)->count();
        }
        if ($canViewPackages) {
            $summary['total_packages'] = Package::query()->count();
        }
        if ($actor->can(PermissionName::ReportsView->value)) {
            $summary['operational_sales_last_30_days'] = $reports->run('sales', ReportFilters::fromValidated([]))['summary'];
            $summary = array_merge($summary, $reports->dashboard(ReportFilters::fromValidated([])));
            $summary['pending_payment_approvals'] = Payment::query()->where('status', 'pending_review')->count();
            $summary['pending_refunds'] = Refund::query()->where('status', 'pending')->count();
        }

        return response()->json(['data' => $summary]);
    }
}
