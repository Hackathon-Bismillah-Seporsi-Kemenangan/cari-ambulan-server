<?php

namespace App\Http\Resources;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DeviceToken
 */
class DeviceTokenResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DeviceToken $token */
        $token = $this->resource;

        return [
            'id' => $token->id,
            'user_id' => (string) $token->user_id,
            'token' => $token->token,
            'device_id' => $token->device_id,
            'device_type' => $token->device_type,
            'device_name' => $token->device_name,
            'is_active' => (bool) $token->is_active,
            'last_used_at' => $token->last_used_at?->toISOString(),
            'created_at' => $token->created_at?->toISOString(),
            'updated_at' => $token->updated_at?->toISOString(),
        ];
    }
}
