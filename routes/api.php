<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\LocationController;
use App\Http\Controllers\Api\V1\Driver\OfferController;
use App\Http\Controllers\Api\V1\Driver\ProfileController;
use App\Http\Controllers\Api\V1\EmergencyRequestController;
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

        // Emergency Request endpoints (Patient / Any authenticated user)
        Route::prefix('emergency-requests')->name('emergency-requests.')->group(function () {
            Route::post('', [EmergencyRequestController::class, 'store'])->name('store');
            Route::get('current', [EmergencyRequestController::class, 'current'])->name('current');
            Route::post('{id}/cancel', [EmergencyRequestController::class, 'cancel'])->name('cancel');
        });

        // Driver endpoints
        Route::middleware('role:driver')->prefix('driver')->name('driver.')->group(function () {
            Route::get('profile', [ProfileController::class, 'show'])->name('profile');
            Route::put('availability', [AvailabilityController::class, 'update'])->name('availability');
            Route::post('location', [LocationController::class, 'store'])->name('location');
            Route::get('offers/current', [OfferController::class, 'current'])->name('offers.current');
        });
    });
});
