<?php

namespace App\Events\Call;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallAccepted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    /**
     * Broadcast to both caller and receiver channels.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $receiverId = $this->call->receiver_id ?? $this->call->callee_id;

        return [
            new PrivateChannel('users.'.$this->call->caller_id.'.calls'),
            new PrivateChannel('users.'.$receiverId.'.calls'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallAccepted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'call_id' => (string) $this->call->id,
            'room_name' => (string) $this->call->room_name,
            'status' => $this->call->status instanceof \BackedEnum ? $this->call->status->value : (string) $this->call->status,
            'caller_id' => (string) $this->call->caller_id,
            'receiver_id' => (string) ($this->call->receiver_id ?? $this->call->callee_id),
            'answered_at' => $this->call->answered_at?->toISOString() ?? $this->call->accepted_at?->toISOString(),
        ];
    }
}
