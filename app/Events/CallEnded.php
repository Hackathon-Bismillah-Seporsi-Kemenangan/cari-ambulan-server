<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallEnded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    /**
     * The channels the event should broadcast on.
     * Broadcasts to both participants (calls channel, and each user channel).
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('calls.'.$this->call->id),
            new PrivateChannel('users.'.$this->call->caller_id),
            new PrivateChannel('users.'.$this->call->callee_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallEnded';
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => (string) $this->call->id,
            'room_name' => (string) $this->call->room_name,
            'status' => $this->call->status instanceof \BackedEnum ? $this->call->status->value : (string) $this->call->status,
            'started_at' => $this->call->started_at?->toISOString(),
            'accepted_at' => $this->call->accepted_at?->toISOString(),
            'ended_at' => $this->call->ended_at?->toISOString(),
            'ended_by_id' => $this->call->ended_by_id ? (string) $this->call->ended_by_id : null,
            'duration_seconds' => (int) ($this->call->duration_seconds ?? 0),
        ];
    }
}
