<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Http\Resources\DispatchOfferResource;
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
}

