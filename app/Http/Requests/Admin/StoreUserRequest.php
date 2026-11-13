<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:100', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'alamat' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role_id' => ['nullable', 'uuid', 'exists:roles,id'],
            'roles' => ['nullable'],
            'roles.*' => ['uuid', 'exists:roles,id'],
            'license_number' => ['nullable', 'string', 'max:20'],
            'hospital_id' => ['nullable', 'uuid', 'exists:hospitals,id'],
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'full_name' => 'nama lengkap',
            'email' => 'alamat email',
            'phone' => 'nomor telepon',
            'alamat' => 'alamat',
            'password' => 'kata sandi',
            'roles' => 'role',
        ];
    }
}

