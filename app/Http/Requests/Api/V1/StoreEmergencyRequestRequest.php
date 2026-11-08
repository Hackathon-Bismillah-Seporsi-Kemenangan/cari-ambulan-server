<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmergencyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'is_for_self' => ['nullable', 'boolean'],
            'emergency_type' => ['nullable', 'string', 'max:50'],
            'severity_level' => ['nullable', 'string', 'in:critical,severe,moderate,mild'],
            'patient_notes' => ['nullable', 'string', 'max:500'],
            'pickup_latitude' => ['required', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['required', 'numeric', 'between:-180,180'],
            'pickup_address' => ['required', 'string', 'max:255'],
        ];
    }
}

