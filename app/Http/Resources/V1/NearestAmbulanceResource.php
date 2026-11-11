<?php

namespace App\Http\Resources\V1;

use App\Models\DispatchLog;
use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DriverProfile
 */
class NearestAmbulanceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // ~100 m precision: enough for the map, without tracking the driver.
            'latitude' => round($this->current_latitude, 3),
            'longitude' => round($this->current_longitude, 3),
            'distance_km' => $this->distance_km,
            'eta_minutes' => DispatchLog::drivingMinutes($this->distance_km),
        ];
    }
}
