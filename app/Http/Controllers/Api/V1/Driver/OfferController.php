<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\DispatchOfferResource;
use App\Http\Resources\EmergencyRequestResource;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    /**
     * Poll the current pending dispatch offer for this driver.
     */
    public function current(Request $request): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if ($driver === null) {
            return response()->json([
                'message' => 'Driver profile tidak ditemukan.',
            ], 404);
        }

        // Run waiting redispatch sweep opportunistically
        EmergencyRequest::redispatchWaiting();

        $offer = $driver->dispatchLogs()
            ->pending()
            ->with(['emergencyRequest.user'])
            ->latest('offered_at')
            ->first();

        if ($offer === null || $offer->secondsRemaining() <= 0) {
            return response()->json(['data' => null]);
        }

        return (new DispatchOfferResource($offer))->response();
    }

    /**
     * Accept an emergency dispatch offer.
     */
    public function accept(Request $request, string $id): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if ($driver === null) {
            return response()->json(['message' => 'Driver profile tidak ditemukan.'], 404);
        }

        /** @var \App\Models\DispatchLog $offer */
        $offer = $driver->dispatchLogs()->where('id', $id)->firstOrFail();

        if ($offer->secondsRemaining() <= 0) {
            return response()->json([
                'message' => 'Tawaran panggilan darurat telah kedaluwarsa.',
            ], 422);
        }

        $emergency = $offer->emergencyRequest;

        if (! $emergency->acceptByDriver($driver, $offer)) {
            return response()->json([
                'message' => 'Panggilan darurat sudah tidak tersedia atau dibatalkan.',
            ], 409);
        }

        return response()->json([
            'message' => 'Panggilan darurat berhasil diterima.',
            'data' => new EmergencyRequestResource($emergency->fresh()),
        ]);
    }

    /**
     * Decline an emergency dispatch offer.
     */
    public function decline(Request $request, string $id): JsonResponse
    {
        $driver = $request->user()->driverProfile;

        if ($driver === null) {
            return response()->json(['message' => 'Driver profile tidak ditemukan.'], 404);
        }

        /** @var \App\Models\DispatchLog $offer */
        $offer = $driver->dispatchLogs()->where('id', $id)->firstOrFail();

        $emergency = $offer->emergencyRequest;
        $emergency->declineByDriver($driver, $offer);

        return response()->json([
            'message' => 'Panggilan darurat berhasil ditolak.',
        ]);
    }
}

