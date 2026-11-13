<?php

namespace App\Http\Resources\V1;

use App\Models\TripCall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripCallResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var TripCall $call */
        $call = $this->resource;

        return [
            'id' => $call->id,
            'status' => $call->status,
            'caller' => [
                'name' => $call->caller?->full_name,
                'phone' => $call->caller?->phone,
            ],
            'request' => [
                'id' => $call->emergencyRequest?->id,
                'code' => $call->emergencyRequest?->request_code,
            ],
            'rang_at' => $call->rang_at?->toIso8601String(),
            'answered_at' => $call->answered_at?->toIso8601String(),
            'ended_at' => $call->ended_at?->toIso8601String(),
            // Lets the app stop ringing on time even if the phone's clock is off.
            'ring_seconds_remaining' => $call->isRinging() ? $call->ringSecondsRemaining() : 0,
            // Call duration so far, likewise independent of the phone's clock.
            'talk_seconds' => $call->answered_at === null ? null : (int) $call->answered_at->diffInSeconds(now()),
        ];
    }
}

