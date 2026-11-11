<?php

namespace App\Http\Resources\V1;

use App\Models\UserMedicalProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin UserMedicalProfile
 */
class UserMedicalProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'blood_type' => $this->blood_type,
            'allergies' => $this->allergies ?? [],
            'medical_conditions' => $this->medical_conditions ?? [],
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'age' => $this->birth_date?->age,
            'gender' => $this->gender,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'height_cm' => $this->height_cm !== null ? (float) $this->height_cm : null,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_relation' => $this->emergency_contact_relation,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'insurance' => $this->insurance,
            'share_on_sos' => (bool) ($this->share_on_sos ?? true),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
