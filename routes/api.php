<?php

use App\Http\Controllers\Api\V1\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\V1\Admin\CourseController as AdminCourseController;
use App\Http\Controllers\Api\V1\Admin\CourseSectionController;
use App\Http\Controllers\Api\V1\Admin\CurriculumOrderController;
use App\Http\Controllers\Api\V1\Admin\EnrollmentAccessController;
use App\Http\Controllers\Api\V1\Admin\LessonController;
use App\Http\Controllers\Api\V1\Admin\LessonResourceController;
use App\Http\Controllers\Api\V1\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\V1\Admin\OrderStatusController;
use App\Http\Controllers\Api\V1\Admin\PackageController as AdminPackageController;
use App\Http\Controllers\Api\V1\Admin\PackageCourseController;
use App\Http\Controllers\Api\V1\Admin\PackageCourseOrderController;
use App\Http\Controllers\Api\V1\Admin\PaymentApprovalController;
use App\Http\Controllers\Api\V1\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\V1\Admin\PaymentProofController as AdminPaymentProofController;
use App\Http\Controllers\Api\V1\Admin\PaymentRejectionController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CourseController;
use App\Http\Controllers\Api\V1\Me\CartController;
use App\Http\Controllers\Api\V1\Me\CartItemController;
use App\Http\Controllers\Api\V1\Me\CheckoutController;
use App\Http\Controllers\Api\V1\Me\CourseController as MeCourseController;
use App\Http\Controllers\Api\V1\Me\LessonProgressController;
use App\Http\Controllers\Api\V1\Me\OrderController as MeOrderController;
use App\Http\Controllers\Api\V1\Me\PaymentController as MePaymentController;
use App\Http\Controllers\Api\V1\Me\PaymentProofController as MePaymentProofController;
use App\Http\Controllers\Api\V1\PackageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('packages/{package:slug}', [PackageController::class, 'show'])->name('packages.show');

    Route::middleware('auth:sanctum')->prefix('me')->name('me.')->group(function (): void {
        Route::get('cart', [CartController::class, 'index'])->name('cart.index');
        Route::delete('cart', [CartController::class, 'destroy'])->name('cart.destroy');
        Route::post('cart/items', [CartItemController::class, 'store'])->name('cart.items.store');
        Route::delete('cart/items/{cartItem}', [CartItemController::class, 'destroy'])->name('cart.items.destroy');
        Route::post('checkout', CheckoutController::class)->middleware('throttle:10,1')->name('checkout.store');
        Route::get('orders', [MeOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [MeOrderController::class, 'show'])->name('orders.show');
        Route::post('orders/{order}/payments', [MePaymentController::class, 'store'])->middleware('throttle:10,1')->name('orders.payments.store');
        Route::get('payments/{payment}/proof', MePaymentProofController::class)->name('payments.proof.show');

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
        Route::apiResource('packages', AdminPackageController::class);
        Route::post('users/{user}/courses/{course}/access', [EnrollmentAccessController::class, 'store'])->name('users.courses.access.store');
        Route::delete('access-grants/{grant}', [EnrollmentAccessController::class, 'destroy'])->name('access-grants.destroy');
        Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/status', OrderStatusController::class)->name('orders.status.update');
        Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/proof', AdminPaymentProofController::class)->name('payments.proof.show');
        Route::post('payments/{payment}/approve', PaymentApprovalController::class)->name('payments.approve');
        Route::post('payments/{payment}/reject', PaymentRejectionController::class)->name('payments.reject');

        Route::scopeBindings()->group(function (): void {
            Route::get('packages/{package}/courses', [PackageCourseController::class, 'index'])->name('packages.courses.index');
            Route::post('packages/{package}/courses', [PackageCourseController::class, 'store'])->name('packages.courses.store');
            Route::post('packages/{package}/courses/reorder', PackageCourseOrderController::class)->name('packages.courses.reorder');
            Route::patch('packages/{package}/courses/{courseMembership}', [PackageCourseController::class, 'update'])->name('packages.courses.update');
            Route::delete('packages/{package}/courses/{courseMembership}', [PackageCourseController::class, 'destroy'])->name('packages.courses.destroy');

            Route::post('courses/{course}/sections/reorder', [CurriculumOrderController::class, 'sections'])->name('courses.sections.reorder');
            Route::apiResource('courses.sections', CourseSectionController::class);

            Route::post('sections/{section}/lessons/reorder', [CurriculumOrderController::class, 'lessons'])->name('sections.lessons.reorder');
            Route::apiResource('sections.lessons', LessonController::class);

            Route::post('lessons/{lesson}/resources/reorder', [CurriculumOrderController::class, 'resources'])->name('lessons.resources.reorder');
            Route::apiResource('lessons.resources', LessonResourceController::class);
        });
    });
});
