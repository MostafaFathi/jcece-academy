<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\V1\Admin\CourseSectionController;
use App\Http\Controllers\Api\V1\Admin\CurriculumOrderController;
use App\Http\Controllers\Api\V1\Admin\LessonController;
use App\Http\Controllers\Api\V1\Admin\LessonResourceController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CourseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');

    Route::middleware('auth:sanctum')->prefix('admin')->name('admin.')->group(function (): void {
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('courses', AdminCourseController::class);

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
