<?php

namespace App\Http\Resources\V1;

use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Past emergency request summary for patient's history list ("Riwayat").
 *
 * @mixin EmergencyRequest
 */
class EmergencyHistoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hospitalName = $this->hospital?->name ?? $this->driverProfile?->hospital?->name;
        $ambulancePlate = $this->ambulance?->plate_number ?? $this->driverProfile?->ambulance?->plate_number;
        $driverName = $this->driverProfile?->user?->full_name ?? $this->driverProfile?->user?->name;

        return [
            'id' => $this->id,
            'code' => $this->request_code,
            'status' => $this->status,
            'emergency_type' => $this->emergency_type,
            'pickup_address' => $this->pickup_address,
            'pickup' => [
                'address' => $this->pickup_address,
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
            ],
            'request_at' => $this->request_at?->toIso8601String(),
            'hospital' => $hospitalName !== null ? ['name' => $hospitalName] : null,
            'hospital_name' => $hospitalName,
            'ambulance' => $ambulancePlate !== null ? ['plate_number' => $ambulancePlate] : null,
            'ambulance_plate' => $ambulancePlate,
            'driver' => $driverName !== null ? ['name' => $driverName] : null,
            'driver_name' => $driverName,
            'cancel_reason' => $this->cancel_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}

