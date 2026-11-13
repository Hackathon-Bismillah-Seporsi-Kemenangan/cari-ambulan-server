<?php

namespace App\Events\Call;

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
     * Broadcast to the receiver's personal calls channel.
     *
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
        return 'CallIncoming';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->call->loadMissing(['caller', 'receiver']);

        return [
            'call_id' => (string) $this->call->id,
            'room_name' => (string) $this->call->room_name,
            'status' => $this->call->status instanceof \BackedEnum ? $this->call->status->value : (string) $this->call->status,
            'caller' => [
                'id' => (string) $this->call->caller_id,
                'name' => $this->call->caller?->full_name ?? '',
                'phone' => $this->call->caller?->phone ?? '',
            ],
            'receiver' => [
                'id' => (string) ($this->call->receiver_id ?? $this->call->callee_id),
                'name' => $this->call->receiver?->full_name ?? '',
                'phone' => $this->call->receiver?->phone ?? '',
            ],
            'started_at' => $this->call->started_at?->toISOString(),
            'created_at' => $this->call->created_at?->toISOString(),
        ];
    }
}
