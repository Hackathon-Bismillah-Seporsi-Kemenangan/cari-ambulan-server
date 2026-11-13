<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $callId,
        public string $senderId,
        public string $type,
        public mixed $data,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('call.'.$this->callId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    public function broadcastWith(): array
    {
        return [
            'call_id' => $this->callId,
            'sender_id' => $this->senderId,
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}

