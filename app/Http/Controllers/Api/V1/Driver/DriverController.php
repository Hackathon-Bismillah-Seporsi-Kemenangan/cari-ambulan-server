<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Http\Controllers\Controller;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;

abstract class DriverController extends Controller
{
    /**
     * The signed-in driver's profile.
     */
    protected function driver(Request $request): DriverProfile
    {
        $driver = $request->user()->driverProfile;

        abort_if($driver === null, 403, 'Akun ini belum memiliki profil driver.');

        return $driver;
    }

    /**
     * Hides requests served by other drivers behind a 404.
     */
    protected function ensureAssigned(EmergencyRequest $emergency, DriverProfile $driver): void
    {
        abort_if($emergency->driver_profile_id !== $driver->id, 404);
    }
}

