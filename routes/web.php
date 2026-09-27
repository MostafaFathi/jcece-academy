<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('login', [SessionController::class, 'store'])->middleware('throttle:5,1')->name('login');
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', [SessionController::class, 'show'])->name('user');
        Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
    });
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!(?:api|sanctum|up|storage|assets|build)(?:/|$)).*$')
    ->name('spa');
