<?php

namespace App\Http\Resources\V1;

use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmergencyRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var EmergencyRequest $emergency */
        $emergency = $this->resource;
        $reporter = $emergency->user;
        // The reporter's Medical ID only describes the patient when they
        // called for themselves.
        $medical = $emergency->is_for_self ? $reporter?->medicalProfile : null;

        return [
            'id' => $emergency->id,
            'code' => $emergency->request_code,
            'status' => $emergency->status,
            'emergency_type' => $emergency->emergency_type,
            'severity_level' => $emergency->severity_level,
            'is_high_priority' => $emergency->severity_level === 'high',
            'is_for_self' => $emergency->is_for_self,
            'patient_notes' => $emergency->patient_notes,
            'pickup' => [
                'latitude' => $emergency->pickup_latitude,
                'longitude' => $emergency->pickup_longitude,
                'address' => $emergency->pickup_address,
            ],
            'reporter' => [
                'name' => $reporter?->full_name ?? $reporter?->name,
                'phone' => $reporter?->phone,
            ],
            'medical' => $medical === null ? null : [
                'blood_type' => $medical->blood_type,
                'allergies' => $medical->allergies ?? [],
                'medical_conditions' => $medical->medical_conditions ?? [],
                'age' => $medical->birth_date?->age,
                'weight_kg' => $medical->weight_kg !== null ? (float) $medical->weight_kg : null,
                'height_cm' => $medical->height_cm !== null ? (float) $medical->height_cm : null,
                'emergency_contact_name' => $medical->emergency_contact_name,
                'emergency_contact_phone' => $medical->emergency_contact_phone,
                'insurance' => $medical->insurance,
            ],
            'hospital' => $emergency->hospital === null ? null : new HospitalResource($emergency->hospital),
            'hospital_preparation' => $emergency->hospital === null ? null : $emergency->igdPreparation(),
            'request_at' => $emergency->request_at?->toIso8601String(),
            'accepted_at' => $emergency->accepted_at?->toIso8601String(),
            'arrived_at_pickup_at' => $emergency->arrived_at_pickup_at?->toIso8601String(),
            'picked_up_at' => $emergency->picked_up_at?->toIso8601String(),
            'completed_at' => $emergency->completed_at?->toIso8601String(),
            'cancelled_at' => $emergency->cancelled_at?->toIso8601String(),
            'cancel_reason' => $emergency->cancel_reason,
        ];
    }
}

