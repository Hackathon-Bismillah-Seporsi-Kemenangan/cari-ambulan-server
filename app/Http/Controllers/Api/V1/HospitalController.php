<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\NearestHospitalRequest;
use App\Http\Resources\V1\HospitalResource;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;

class HospitalController extends Controller
{
    public function nearest(NearestHospitalRequest $request): HospitalResource|JsonResponse
    {
        $hospital = Hospital::nearestTo($request->float('latitude'), $request->float('longitude'));

        return $hospital === null
            ? response()->json(['data' => null])
            : new HospitalResource($hospital);
    }
}
