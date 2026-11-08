<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DriverAvailability;
use App\Http\Requests\Api\V1\UpdateAvailabilityRequest;
use App\Http\Resources\V1\DriverProfileResource;

class AvailabilityController extends DriverController
{
    public function update(UpdateAvailabilityRequest $request): DriverProfileResource
    {
        $driver = $this->driver($request);

        $goingOnline = $request->string('status')->toString() === DriverAvailability::Online->value;

        // Driver must have an active ambulance before going online
        abort_if(
            $goingOnline && ! $driver->ambulance()->exists(),
            409,
            'Akun Anda belum terhubung dengan ambulans. Hubungi admin untuk mendaftarkan kendaraan.',
        );

        $driver->update([
            'availability_status' => $request->string('status')->toString(),
            ...($goingOnline ? [
                'current_latitude' => $request->float('latitude'),
                'current_longitude' => $request->float('longitude'),
                'location_updated_at' => now(),
            ] : []),
        ]);

        return new DriverProfileResource($driver->load(['user', 'ambulance', 'hospital']));
    }
}

