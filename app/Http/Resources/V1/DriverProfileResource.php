<?php

namespace App\Http\Resources\V1;

use App\Models\DriverProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DriverProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DriverProfile $driver */
        $driver = $this->resource;
        $ambulance = $driver->ambulance;

        return [
            'id' => $driver->id,
            'name' => $driver->user->full_name,
            'phone' => $driver->user->phone,
            'license_number' => $driver->license_number,
            'availability_status' => $driver->availability_status instanceof \BackedEnum
                ? $driver->availability_status->value
                : (string) $driver->availability_status,
            'location' => $driver->current_latitude === null ? null : [
                'latitude' => $driver->current_latitude,
                'longitude' => $driver->current_longitude,
                'updated_at' => $driver->location_updated_at?->toIso8601String(),
            ],
            'vehicle' => $ambulance === null ? null : [
                'plate_number' => $ambulance->plate_number,
                'vehicle_type' => $ambulance->vehicle_type,
            ],
            'hospital' => $driver->hospital === null ? null : [
                'id' => $driver->hospital->id,
                'name' => $driver->hospital->name,
            ],
        ];
    }
}

