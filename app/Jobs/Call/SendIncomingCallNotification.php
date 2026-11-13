<?php

namespace App\Jobs\Call;

use App\Models\Call;
use App\Services\Notification\FcmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendIncomingCallNotification implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $callId) {}

    public function handle(FcmService $fcm): void
    {
        $call = Call::with(['caller', 'receiver.deviceTokens'])->find($this->callId);

        if (! $call || ! $call->canBeAccepted()) {
            return;
        }

        $receiver = $call->receiver;
        if ($receiver) {
            $fcm->sendIncomingCall($call, $receiver, $call->caller?->full_name);
        }
    }
}
