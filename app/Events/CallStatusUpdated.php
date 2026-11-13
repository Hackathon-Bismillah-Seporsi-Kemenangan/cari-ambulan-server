<?php

namespace App\Events;

use App\Enums\CallStatus;
use App\Models\TripCall;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TripCall $call,
        public string $status,
    ) {}

    /**
     * Broadcasts without failing the request: the call's state is already
     * saved and both apps also poll it, so a WebSocket outage is reported
     * instead of thrown.
     */
    public static function broadcastSafely(TripCall $call, CallStatus $status): void
    {
        rescue(fn () => event(new self($call, $status->value)));
    }

    public function broadcastOn(): array
    {
        $channels = [
            new Channel('call.'.$this->call->id),
            new Channel('user.'.$this->call->caller_id),
        ];

        if ($this->call->emergencyRequest?->driver_profile_id) {
            $channels[] = new Channel('driver.'.$this->call->emergencyRequest->driver_profile_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'CallStatusUpdated';
    }

    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'emergencyRequest.driverProfile']);

        return [
            'call_id' => $this->call->id,
            'status' => $this->status,
            'emergency_request_id' => $this->call->emergency_request_id,
            'caller_id' => $this->call->caller_id,
            'caller_name' => $this->call->caller?->full_name,
            'caller_phone' => $this->call->caller?->phone,
            'emergency_type' => $this->call->emergencyRequest?->emergency_type,
            'pickup_address' => $this->call->emergencyRequest?->pickup_address,
            'rang_at' => $this->call->rang_at?->toIso8601String(),
            'answered_at' => $this->call->answered_at?->toIso8601String(),
            'ended_at' => $this->call->ended_at?->toIso8601String(),
        ];
    }
}

