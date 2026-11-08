<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\DriverAvailability;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'required',
                Rule::in([
                    DriverAvailability::Online->value,
                    DriverAvailability::Offline->value,
                ]),
            ],
            // Latitude & longitude are required when switching online
            'latitude' => ['required_if:status,online', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_if:status,online', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.required' => 'Status ketersediaan wajib diisi.',
            'status.in' => 'Status harus berupa online atau offline.',
            'latitude.required_if' => 'Koordinat latitude wajib dikirim saat mengaktifkan status online.',
            'longitude.required_if' => 'Koordinat longitude wajib dikirim saat mengaktifkan status online.',
        ];
    }
}

