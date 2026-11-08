<?php

namespace App\Http\Resources;

use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EmergencyRequest
 */
class EmergencyRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $driverProfile = $this->driverProfile;
        $ambulance = $this->ambulance ?? $driverProfile?->ambulance;
        $hospital = $this->hospital ?? $driverProfile?->hospital;

        return [
            'id' => $this->id,
            'request_code' => $this->request_code,
            'status' => $this->status,
            'is_for_self' => (bool) $this->is_for_self,
            'emergency_type' => $this->emergency_type,
            'severity_level' => $this->severity_level,
            'patient_notes' => $this->patient_notes,
            'pickup' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'address' => $this->pickup_address,
            ],
            'search' => [
                'radius_km' => $this->search_radius_km,
                'is_exhausted' => $this->search_exhausted_at !== null,
            ],
            'assigned_driver' => $driverProfile ? [
                'id' => $driverProfile->id,
                'name' => $driverProfile->user?->full_name ?? $driverProfile->user?->name,
                'phone' => $driverProfile->user?->phone,
                'license_number' => $driverProfile->license_number,
                'location' => [
                    'latitude' => $driverProfile->current_latitude,
                    'longitude' => $driverProfile->current_longitude,
                    'updated_at' => $driverProfile->location_updated_at?->toIso8601String(),
                ],
            ] : null,
            'ambulance' => $ambulance ? [
                'id' => $ambulance->id,
                'plate_number' => $ambulance->plate_number,
                'vehicle_model' => $ambulance->vehicle_model,
                'ambulance_type' => $ambulance->ambulance_type,
            ] : null,
            'hospital' => $hospital ? [
                'id' => $hospital->id,
                'name' => $hospital->name,
                'address' => $hospital->address,
                'phone' => $hospital->phone,
            ] : null,
            'timeline' => [
                'request_at' => $this->request_at?->toIso8601String(),
                'accepted_at' => $this->accepted_at?->toIso8601String(),
                'arrived_at_pickup_at' => $this->arrived_at_pickup_at?->toIso8601String(),
                'picked_up_at' => $this->picked_up_at?->toIso8601String(),
                'completed_at' => $this->completed_at?->toIso8601String(),
                'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            ],
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
