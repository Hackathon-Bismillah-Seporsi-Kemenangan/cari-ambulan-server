<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartCallRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $userId = (string) $this->user()?->id;

        return [
            'receiver_id' => [
                'required_without:callee_id',
                'uuid',
                'exists:users,id',
                Rule::notIn([$userId]),
            ],
            'callee_id' => [
                'nullable',
                'uuid',
                'exists:users,id',
                Rule::notIn([$userId]),
            ],
            'call_type' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get validated receiver UUID.
     */
    public function getReceiverId(): string
    {
        return (string) ($this->input('receiver_id') ?? $this->input('callee_id'));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receiver_id.not_in' => 'Tidak dapat memanggil diri sendiri.',
            'callee_id.not_in' => 'Tidak dapat memanggil diri sendiri.',
        ];
    }
}
