<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\EmergencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmergencyRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    /**
     * Get the active emergency trip being served by this driver.
     */
    public function current(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if ($driver === null) {
            return response()->json(['message' => 'Driver profile tidak ditemukan.'], 404);
        }

        $trip = $driver->activeTrip();

        if ($trip === null) {
            return response()->json(['data' => null]);
        }

        return (new EmergencyRequestResource($trip))->response();
    }

    /**
     * Advance the emergency trip to the next status.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:en_route_to_pickup,arrive_at_pickup,en_route_to_hospital,completed',
            ],
        ]);

        $driver = $request->user()->driverProfile;

        if ($driver === null) {
            return response()->json(['message' => 'Driver profile tidak ditemukan.'], 404);
        }

        $trip = $driver->activeTrip();

        if ($trip === null) {
            return response()->json([
                'message' => 'Tidak ada perjalanan darurat yang sedang aktif.',
            ], 404);
        }

        $targetStatus = EmergencyStatus::from($validated['status']);

        if (! $trip->progressTripStatus($targetStatus)) {
            return response()->json([
                'message' => "Transisi status ke '{$targetStatus->value}' tidak diizinkan dari status saat ini '{$trip->status}'.",
            ], 422);
        }

        return response()->json([
            'message' => 'Status perjalanan darurat berhasil diperbarui.',
            'data' => new EmergencyRequestResource($trip->fresh()),
        ]);
    }
}

