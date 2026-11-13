<?php

namespace App\Jobs\Call;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallMissed;
use App\Models\Call;
use App\Services\Call\LiveKitService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ExpireRingingCall implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $callId) {}

    public function handle(LiveKitService $liveKit): void
    {
        $call = DB::transaction(function (): ?Call {
            $call = Call::lockForUpdate()->find($this->callId);

            if (! $call || ! $call->canBeAccepted()) {
                return null;
            }

            // On sync queue driver, prevent premature expiration if call was just placed
            $timeoutSeconds = (int) config('livekit.ringing_timeout', 30);
            if ($call->started_at && $call->started_at->diffInSeconds(now()) < ($timeoutSeconds - 2)) {
                return null;
            }

            $call->update([
                'status' => CallStatus::Missed,
                'end_reason' => CallEndReason::Timeout,
                'ended_at' => now(),
            ]);

            return $call;
        });

        if ($call) {
            $call->load(['caller', 'receiver']);
            broadcast(new CallMissed($call));
            $liveKit->deleteRoom($call->room_name);
        }
    }
}
