<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NearestAmbulanceRequest;
use App\Http\Resources\V1\NearestAmbulanceResource;
use App\Models\DriverProfile;
use Illuminate\Http\JsonResponse;

class AmbulanceController extends Controller
{
    /**
     * The closest ambulance that could take a call right now, for the
     * "Ambulans terdekat ±4 menit" hint on the user's home screen.
     */
    public function nearest(NearestAmbulanceRequest $request): NearestAmbulanceResource|JsonResponse
    {
        $driver = DriverProfile::nearestDispatchableTo(
            $request->float('latitude'),
            $request->float('longitude'),
        )->first();

        return $driver === null
            ? response()->json(['data' => null])
            : new NearestAmbulanceResource($driver);
    }
}
