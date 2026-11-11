<?php

use App\Http\Controllers\Api\V1\AmbulanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\LocationController;
use App\Http\Controllers\Api\V1\Driver\OfferController;
use App\Http\Controllers\Api\V1\Driver\ProfileController;
use App\Http\Controllers\Api\V1\Driver\TripController;
use App\Http\Controllers\Api\V1\EmergencyRequestController;
use App\Http\Controllers\Api\V1\HospitalController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MedicalProfileController;
use App\Http\Controllers\Api\V1\UserAddressController;
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
        Route::put('auth/me', [MeController::class, 'update']);
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        // Profile, medical ID & saved addresses
        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::put('me', [MeController::class, 'update'])->name('me.update');
        Route::get('me/medical-profile', [MedicalProfileController::class, 'show'])->name('me.medical-profile.show');
        Route::put('me/medical-profile', [MedicalProfileController::class, 'update'])->name('me.medical-profile.update');
        Route::get('me/addresses', [UserAddressController::class, 'index'])->name('me.addresses.index');
        Route::post('me/addresses', [UserAddressController::class, 'store'])->name('me.addresses.store');
        Route::delete('me/addresses/{userAddress}', [UserAddressController::class, 'destroy'])->name('me.addresses.destroy');

        // Nearest hospital & ambulance discovery
        Route::get('hospitals/nearest', [HospitalController::class, 'nearest'])->name('hospitals.nearest');
        Route::get('ambulances/nearest', [AmbulanceController::class, 'nearest'])
            ->middleware('role:user')
            ->name('ambulances.nearest');

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
            Route::post('offers/{id}/accept', [OfferController::class, 'accept'])->name('offers.accept');
            Route::post('offers/{id}/decline', [OfferController::class, 'decline'])->name('offers.decline');

            // Trip lifecycle endpoints
            Route::get('trip/current', [TripController::class, 'current'])->name('trip.current');
            Route::put('trip/status', [TripController::class, 'updateStatus'])->name('trip.status');
        });
    });
});
