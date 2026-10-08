<?php

use App\Http\Controllers\Api\V1\Auth\NewPasswordController;
use App\Http\Controllers\Api\V1\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->middleware(SetRequestLocale::class)->name('api.v1.auth.')->group(function (): void {
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login');
    Route::post('register', RegistrationController::class)->middleware('throttle:3,1')->name('register');
    Route::post('forgot-password', PasswordResetLinkController::class)->middleware('throttle:5,1')->name('password.email');
    Route::post('reset-password', NewPasswordController::class)->middleware('throttle:5,1')->name('password.update');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', [SessionController::class, 'show'])->name('user');
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    });
});

Route::view('/certificates/verify/{token}', 'app')->name('certificates.verify.page');

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!(?:api|sanctum|up|storage|assets|build)(?:/|$)).*$')
    ->name('spa');
