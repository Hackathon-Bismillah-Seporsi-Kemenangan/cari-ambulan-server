<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Requests\Api\V1\StoreLocationRequest;
use Illuminate\Http\Response;

class LocationController extends DriverController
{
    /**
     * Update driver's latest GPS beacon coordinates.
     */
    public function store(StoreLocationRequest $request): Response
    {
        $driver = $this->driver($request);

        $driver->update([
            'current_latitude' => $request->float('latitude'),
            'current_longitude' => $request->float('longitude'),
            'location_updated_at' => now(),
        ]);

        return response()->noContent();
    }
}

