<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Requests\Api\V1\StoreLocationRequest;
use Illuminate\Http\Response;

class LocationController extends DriverController
{
    /**
     * Latest GPS fix; also recorded on the active trip's route log.
     */
    public function store(StoreLocationRequest $request): Response
    {
        $driver = $this->driver($request);
        $latitude = $request->float('latitude');
        $longitude = $request->float('longitude');

        $driver->update([
            'current_latitude' => $latitude,
            'current_longitude' => $longitude,
            'location_updated_at' => now(),
        ]);

        $driver->activeTrip()?->tripLocationLogs()->create([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => now(),
        ]);

        return response()->noContent();
    }
}
