<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API v1 — authenticated with Sanctum bearer tokens
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:6,1')
        ->name('auth.register');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me'])->name('auth.me');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Driver endpoints
        Route::middleware('role:driver')->prefix('driver')->name('driver.')->group(function () {
            Route::get('profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('availability', [AvailabilityController::class, 'update'])->name('availability');
        });
    });
});
