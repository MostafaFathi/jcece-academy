<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\V1\Admin\CourseSectionController;
use App\Http\Controllers\Api\V1\Admin\CurriculumOrderController;
use App\Http\Controllers\Api\V1\Admin\EnrollmentAccessController;
use App\Http\Controllers\Api\V1\Admin\LessonController;
use App\Http\Controllers\Api\V1\Admin\LessonResourceController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\Me\CourseController as MeCourseController;
use App\Http\Controllers\Api\V1\Me\LessonProgressController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');

    Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function (): void {
        Route::get('courses', [MeCourseController::class, 'index'])->name('courses.index');
        Route::get('courses/{course:slug}', [MeCourseController::class, 'show'])->name('courses.show');
        Route::get('courses/{course:slug}/learn', [MeCourseController::class, 'learn'])->name('courses.learn');
        Route::get('courses/{course:slug}/progress', [MeCourseController::class, 'progress'])->name('courses.progress');

        Route::scopeBindings()->group(function (): void {
            Route::patch('courses/{course:slug}/lessons/{lesson}/progress', [LessonProgressController::class, 'update'])->name('courses.lessons.progress.update');
            Route::post('courses/{course:slug}/lessons/{lesson}/complete', [LessonProgressController::class, 'complete'])->name('courses.lessons.complete');
        });
    });

    Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('courses', AdminCourseController::class);
        Route::post('users/{user}/courses/{course}/access', [EnrollmentAccessController::class, 'store'])->name('users.courses.access.store');
        Route::delete('access-grants/{grant}', [EnrollmentAccessController::class, 'destroy'])->name('access-grants.destroy');

        Route::scopeBindings()->group(function (): void {
            Route::post('courses/{course}/sections/reorder', [CurriculumOrderController::class, 'sections'])->name('courses.sections.reorder');
            Route::apiResource('courses.sections', CourseSectionController::class);

            Route::post('sections/{section}/lessons/reorder', [CurriculumOrderController::class, 'lessons'])->name('sections.lessons.reorder');
            Route::apiResource('sections.lessons', LessonController::class);

            Route::post('lessons/{lesson}/resources/reorder', [CurriculumOrderController::class, 'resources'])->name('lessons.resources.reorder');
            Route::apiResource('lessons.resources', LessonResourceController::class);
        });
    });
});
