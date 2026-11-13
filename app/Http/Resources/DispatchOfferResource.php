<?php

namespace App\Http\Resources;

use App\Http\Resources\V1\EmergencyRequestResource;
use App\Models\DispatchLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DispatchLog
 */
class DispatchOfferResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $emergency = $this->emergencyRequest;
        $patient = $emergency?->user;

        return [
            'id' => $this->id,
            'emergency_request_id' => $this->emergency_request_id,
            'request_code' => $emergency?->request_code,
            'status' => $this->status,
            'distance_km' => $this->distance_km,
            'seconds_remaining' => $this->secondsRemaining(),
            'emergency_type' => $emergency?->emergency_type,
            'severity_level' => $emergency?->severity_level,
            'patient_notes' => $emergency?->patient_notes,
            'pickup' => [
                'latitude' => $emergency?->pickup_latitude,
                'longitude' => $emergency?->pickup_longitude,
                'address' => $emergency?->pickup_address,
            ],
            'patient' => [
                'name' => $patient?->full_name ?? $patient?->name ?? 'Pasien',
                'phone' => $patient?->phone,
            ],
            'request' => $emergency ? new EmergencyRequestResource($emergency) : null,
            'offered_at' => $this->offered_at?->toIso8601String(),
        ];
    }
}
