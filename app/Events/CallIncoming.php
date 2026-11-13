<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallIncoming implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    /**
     * The channels the event should broadcast on.
     * Broadcasts to the callee's personal private channel and the call channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('users.'.$this->call->callee_id),
            new PrivateChannel('calls.'.$this->call->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallIncoming';
    }

    /**
     * Data to broadcast with the event.
     * Note: Never include LiveKit JWT tokens or API secrets here.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'callee']);

        return [
            'call_id' => (string) $this->call->id,
            'room_name' => (string) $this->call->room_name,
            'call_type' => $this->call->call_type,
            'status' => $this->call->status instanceof \BackedEnum ? $this->call->status->value : (string) $this->call->status,
            'caller' => [
                'id' => (string) $this->call->caller_id,
                'name' => $this->call->caller?->full_name ?? '',
                'phone' => $this->call->caller?->phone ?? '',
            ],
            'callee' => [
                'id' => (string) $this->call->callee_id,
                'name' => $this->call->callee?->full_name ?? '',
                'phone' => $this->call->callee?->phone ?? '',
            ],
            'started_at' => $this->call->started_at?->toISOString(),
            'metadata' => $this->call->metadata ?? [],
        ];
    }
}
