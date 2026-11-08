<?php

namespace App\Http\Requests\Api\V1;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
            'full_name' => ['required', 'string', 'max:200'],
            'email' => ['bail', 'required', 'string', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'max:20'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'password' => [
                'bail', 'required', 'string', 'min:8', 'max:72', 'confirmed',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (strlen($value) > 72) {
                        $fail('Kata sandi terlalu panjang (maksimal 72 byte).');
                    }
                },
            ],
            'password_confirmation' => ['required', 'string'],
            'role' => ['required', 'string', Rule::in(['user', 'driver'])],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'full_name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk.',
            'phone.required' => 'Nomor HP wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'role.in' => 'Peran yang dipilih tidak valid (hanya user atau driver).',
        ];
    }
}

