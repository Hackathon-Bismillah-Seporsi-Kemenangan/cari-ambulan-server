<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalProfileRequest extends FormRequest
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
            'blood_type' => ['nullable', 'string', 'max:10'],
            'allergies' => ['nullable'],
            'medical_conditions' => ['nullable'],
            'birth_date' => ['nullable', 'date'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
            'gender' => ['nullable', 'string', 'max:20'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'insurance' => ['nullable', 'string', 'max:100'],
            'share_on_sos' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedMedicalAttributes(): array
    {
        $validated = $this->validated();

        if (array_key_exists('allergies', $validated)) {
            $validated['allergies'] = $this->normalizeArrayField($validated['allergies']);
        }

        if (array_key_exists('medical_conditions', $validated)) {
            $validated['medical_conditions'] = $this->normalizeArrayField($validated['medical_conditions']);
        }

        if (empty($validated['birth_date']) && ! empty($validated['age'])) {
            $validated['birth_date'] = now()->subYears((int) $validated['age'])->format('Y-m-d');
        }
        unset($validated['age']);

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function normalizeArrayField(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
    }
}
