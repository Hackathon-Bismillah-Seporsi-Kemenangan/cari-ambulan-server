<?php

namespace App\Events\Call;

use App\Models\Call;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallCancelled implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Call $call) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        $receiverId = $this->call->receiver_id ?? $this->call->callee_id;

        return [
            new PrivateChannel('users.'.$receiverId.'.calls'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallCancelled';
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
            'end_reason' => $this->call->end_reason instanceof \BackedEnum ? $this->call->end_reason->value : (string) $this->call->end_reason,
            'ended_at' => $this->call->ended_at?->toISOString(),
        ];
    }
}
