<?php

namespace App\Http\Resources\V1;

use App\Models\Hospital;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HospitalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Hospital $hospital */
        $hospital = $this->resource;

        return [
            'id' => $hospital->id,
            'name' => $hospital->name,
            'address' => $hospital->address,
            'emergency_phone' => $hospital->emergency_phone,
            'latitude' => $hospital->latitude,
            'longitude' => $hospital->longitude,
            'ambulance_entrance' => $hospital->ambulance_entrance,
            // Only set when the hospital was looked up relative to a point.
            'distance_km' => $this->when(isset($hospital->distance_km), fn () => $hospital->distance_km),
        ];
    }
}
