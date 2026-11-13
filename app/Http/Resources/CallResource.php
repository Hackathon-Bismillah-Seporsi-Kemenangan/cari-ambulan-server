<?php

namespace App\Http\Resources;

use App\Models\Call;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Call
 */
class CallResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Call $call */
        $call = $this->resource;

        $receiver = $call->receiver;
        $statusValue = $call->status instanceof \BackedEnum ? $call->status->value : (string) $call->status;
        $endReasonValue = $call->end_reason instanceof \BackedEnum ? $call->end_reason->value : ($call->end_reason ? (string) $call->end_reason : null);

        return [
            'id' => (string) $call->id,
            'caller_id' => (string) $call->caller_id,
            'receiver_id' => (string) ($call->receiver_id ?? $call->callee_id),
            'room_name' => (string) $call->room_name,
            'call_type' => $call->call_type ?? 'audio',
            'status' => $statusValue,
            'end_reason' => $endReasonValue,
            'caller' => [
                'id' => (string) $call->caller_id,
                'name' => $call->caller?->full_name ?? '',
                'phone' => $call->caller?->phone ?? '',
            ],
            'receiver' => [
                'id' => (string) ($call->receiver_id ?? $call->callee_id),
                'name' => $receiver?->full_name ?? '',
                'phone' => $receiver?->phone ?? '',
            ],
            // Backward compatibility callee key
            'callee' => [
                'id' => (string) ($call->receiver_id ?? $call->callee_id),
                'name' => $receiver?->full_name ?? '',
                'phone' => $receiver?->phone ?? '',
            ],
            'started_at' => $call->started_at?->toISOString(),
            'answered_at' => $call->answered_at?->toISOString() ?? $call->accepted_at?->toISOString(),
            'accepted_at' => $call->accepted_at?->toISOString() ?? $call->answered_at?->toISOString(),
            'ended_at' => $call->ended_at?->toISOString(),
            'ended_by_id' => $call->ended_by_id ? (string) $call->ended_by_id : null,
            'duration_seconds' => $call->duration_seconds,
            'metadata' => $call->metadata ?? [],
            'created_at' => $call->created_at?->toISOString(),
            'updated_at' => $call->updated_at?->toISOString(),
        ];
    }
}
