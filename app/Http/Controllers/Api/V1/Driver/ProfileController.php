<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Resources\V1\DriverProfileResource;
use Illuminate\Http\Request;

class ProfileController extends DriverController
{
    public function show(Request $request): DriverProfileResource
    {
        $driver = $this->driver($request)->load(['user', 'ambulance', 'hospital']);

        return new DriverProfileResource($driver);
    }
}

