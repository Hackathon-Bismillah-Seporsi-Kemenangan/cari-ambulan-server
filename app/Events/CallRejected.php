<?php

namespace App\Events;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallRejected implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Call $call,
        public ?string $reason = null
    ) {}

    /**
     * The channels the event should broadcast on.
     * Broadcasts to caller's personal private channel and the active call channel.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('calls.'.$this->call->id),
            new PrivateChannel('users.'.$this->call->caller_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallRejected';
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
            'caller_id' => (string) $this->call->caller_id,
            'callee_id' => (string) $this->call->callee_id,
            'ended_by_id' => (string) $this->call->ended_by_id,
            'reason' => $this->reason ?? 'declined',
            'ended_at' => $this->call->ended_at?->toISOString(),
        ];
    }
}
