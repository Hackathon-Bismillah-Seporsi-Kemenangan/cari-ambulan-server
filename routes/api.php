<?php

use App\Http\Controllers\Api\V1\AmbulanceController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\CallSignalingController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\Driver\AvailabilityController;
use App\Http\Controllers\Api\V1\Driver\CallController as DriverCallController;
use App\Http\Controllers\Api\V1\Driver\LocationController;
use App\Http\Controllers\Api\V1\Driver\OfferController;
use App\Http\Controllers\Api\V1\Driver\ProfileController;
use App\Http\Controllers\Api\V1\Driver\TripController;
use App\Http\Controllers\Api\V1\EmergencyRequestController;
use App\Http\Controllers\Api\V1\HospitalController;
use App\Http\Controllers\Api\V1\LiveKitWebhookController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MedicalProfileController;
use App\Http\Controllers\Api\V1\TripCallController;
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

    Route::post('webhooks/livekit', [LiveKitWebhookController::class, 'handle'])
        ->name('webhooks.livekit');
    Route::post('livekit/webhook', [LiveKitWebhookController::class, 'handle'])
        ->name('livekit.webhook');

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
            Route::get('', [EmergencyRequestController::class, 'index'])->name('index');
            Route::post('', [EmergencyRequestController::class, 'store'])->name('store');
            Route::get('current', [EmergencyRequestController::class, 'current'])->name('current');
            Route::get('{emergencyRequest}', [EmergencyRequestController::class, 'show'])->name('show');
            Route::post('{emergencyRequest}/cancel', [EmergencyRequestController::class, 'cancel'])->name('cancel');
            Route::post('{emergencyRequest}/radius', [EmergencyRequestController::class, 'widenRadius'])->name('radius');
            Route::post('{emergencyRequest}/retry', [EmergencyRequestController::class, 'retrySearch'])->name('retry');
            Route::post('{emergencyRequest}/calls', [TripCallController::class, 'store'])->name('calls.store');
        });

        // Device token management (FCM)
        Route::get('device-tokens', [DeviceTokenController::class, 'index'])->name('device-tokens.index');
        Route::post('device-tokens', [DeviceTokenController::class, 'store'])->name('device-tokens.store');
        Route::delete('device-tokens', [DeviceTokenController::class, 'destroy'])->name('device-tokens.destroy');

        // WebRTC Signaling & Call Management for both Driver & User
        Route::get('calls/incoming', [CallSignalingController::class, 'incoming'])->name('calls.incoming');

        // App-to-App LiveKit Calls
        Route::prefix('calls')->name('calls.')->group(function () {
            Route::get('/', [CallController::class, 'index'])->name('index');
            Route::post('/', [CallController::class, 'store'])->name('store');
            Route::get('{call}', [CallController::class, 'show'])->name('show');
            Route::post('{call}/accept', [CallController::class, 'accept'])->name('accept');
            Route::post('{call}/reject', [CallController::class, 'reject'])->name('reject');
            Route::post('{call}/cancel', [CallController::class, 'cancel'])->name('cancel');
            Route::post('{call}/end', [CallController::class, 'end'])->name('end');
            Route::match(['get', 'post'], '{call}/token', [CallController::class, 'token'])->name('token');
        });

        // WebRTC Signaling & Actions for Trip Calls
        Route::prefix('calls/{tripCall}')->name('calls.')->group(function () {
            Route::get('/', [CallSignalingController::class, 'show'])->name('show');
            Route::post('answer', [CallSignalingController::class, 'answer'])->name('answer');
            Route::post('decline', [CallSignalingController::class, 'decline'])->name('decline');
            Route::post('end', [CallSignalingController::class, 'end'])->name('end');
            Route::post('signal', [CallSignalingController::class, 'signal'])->name('signal');
            Route::get('ice-servers', [CallSignalingController::class, 'iceServers'])->name('ice-servers');
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
            Route::get('trip', [TripController::class, 'current'])->name('trip');
            Route::get('trip/current', [TripController::class, 'current'])->name('trip.current');
            Route::get('trips', [TripController::class, 'index'])->name('trips.index');
            Route::put('trip/status', [TripController::class, 'updateStatus'])->name('trip.status');
            Route::get('calls/current', [DriverCallController::class, 'current'])->name('calls.current');

            Route::prefix('calls/{tripCall}')->name('calls.')->group(function () {
                Route::post('answer', [DriverCallController::class, 'answer'])->name('answer');
                Route::post('decline', [DriverCallController::class, 'decline'])->name('decline');
                Route::post('end', [DriverCallController::class, 'end'])->name('end');
            });

            Route::prefix('requests/{emergencyRequest}')->name('requests.')->group(function () {
                Route::post('accept', [TripController::class, 'accept'])->name('accept');
                Route::post('decline', [TripController::class, 'decline'])->name('decline');
                Route::post('arrive', [TripController::class, 'arrive'])->name('arrive');
                Route::post('start-to-hospital', [TripController::class, 'startToHospital'])->name('start-to-hospital');
                Route::post('complete', [TripController::class, 'complete'])->name('complete');
            });
        });
    });
});
