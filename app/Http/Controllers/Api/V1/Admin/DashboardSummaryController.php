<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Course;
use App\Models\Package;
use App\Models\User;
use App\PermissionName;
use App\RoleName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardSummaryController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $actor = $request->user();
        $canViewCategories = $actor->can(PermissionName::CategoriesView->value);
        $canViewCourses = $actor->can(PermissionName::CoursesView->value);
        $canViewUsers = $actor->can(PermissionName::UsersView->value);
        $canViewInstructors = $actor->can(PermissionName::InstructorsView->value);
        $canViewPackages = $actor->can(PermissionName::PackagesView->value);
        abort_unless($canViewCategories || $canViewCourses || $canViewUsers || $canViewInstructors || $canViewPackages, 403);

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

        return response()->json(['data' => $summary]);
    }
}
