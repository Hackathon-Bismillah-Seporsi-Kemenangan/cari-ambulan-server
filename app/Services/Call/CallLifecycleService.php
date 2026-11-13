<?php

namespace App\Services\Call;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallAccepted;
use App\Events\Call\CallCancelled;
use App\Events\Call\CallEnded;
use App\Events\Call\CallIncoming;
use App\Events\Call\CallRejected;
use App\Jobs\Call\CleanupCallRoom;
use App\Jobs\Call\ExpireRingingCall;
use App\Jobs\Call\SendIncomingCallNotification;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CallLifecycleService
{
    public function __construct(
        protected LiveKitService $liveKitService,
        protected LiveKitTokenService $tokenService
    ) {}

    /**
     * Start a new 1-on-1 audio call.
     *
     * @param  array<string, mixed>  $options
     * @return array{call: Call, connection: array{server_url: string, token: string, room_name: string, identity: string, expires_in: int}}
     */
    public function startCall(User $caller, string $receiverId, array $options = []): array
    {
        // Concurrency lock to prevent race condition when initiating calls
        $lockKey = 'call_init_lock_'.min((string) $caller->id, $receiverId).'_'.max((string) $caller->id, $receiverId);
        $lock = Cache::lock($lockKey, 10);

        return $lock->block(5, function () use ($caller, $receiverId, $options) {
            // Verify neither party is already in an active/ongoing call
            if (Call::query()->forUser($caller)->ongoing()->exists()) {
                throw new ConflictHttpException('Anda masih memiliki panggilan yang sedang berlangsung.');
            }

            if (Call::query()->forUser($receiverId)->ongoing()->exists()) {
                throw new ConflictHttpException('User tujuan sedang dalam panggilan lain.');
            }

            $roomName = $this->liveKitService->generateRoomName();

            // 1. Create Call record and participant rows in a single transaction
            $call = DB::transaction(function () use ($caller, $receiverId, $roomName, $options): Call {
                $call = Call::create([
                    'caller_id' => $caller->id,
                    'receiver_id' => $receiverId,
                    'callee_id' => $receiverId,
                    'room_name' => $roomName,
                    'call_type' => $options['call_type'] ?? 'audio',
                    'status' => CallStatus::Ringing,
                    'started_at' => now(),
                    'metadata' => $options['metadata'] ?? null,
                ]);

                // Record caller participant
                CallParticipant::create([
                    'call_id' => $call->id,
                    'user_id' => $caller->id,
                    'livekit_identity' => (string) $caller->id,
                ]);

                // Record receiver participant
                CallParticipant::create([
                    'call_id' => $call->id,
                    'user_id' => $receiverId,
                    'livekit_identity' => (string) $receiverId,
                ]);

                return $call;
            });

            // 2. Request LiveKit Cloud room creation
            $this->liveKitService->createRoom($roomName);

            // 3. Generate connection credentials for the caller
            $connection = $this->tokenService->createAudioTokenCredentials($call, $caller);

            // 4. Dispatch background FCM push notification
            SendIncomingCallNotification::dispatch((string) $call->id);

            // 5. Schedule ringing expiration job (default 30 seconds)
            $timeoutSeconds = (int) config('livekit.ringing_timeout', 30);
            ExpireRingingCall::dispatch((string) $call->id)->delay(now()->addSeconds($timeoutSeconds));

            // 6. Broadcast incoming call event
            $call->load(['caller', 'receiver']);
            broadcast(new CallIncoming($call));

            return [
                'call' => $call,
                'connection' => $connection,
            ];
        });
    }

    /**
     * Accept a ringing call as the receiver.
     *
     * @return array{call: Call, connection: array{server_url: string, token: string, room_name: string, identity: string, expires_in: int}}
     */
    public function acceptCall(Call $call, User $receiver): array
    {
        if (! $call->isReceiver($receiver)) {
            abort(403, 'Hanya penerima panggilan yang dapat menerima panggilan.');
        }

        if (! $call->canBeAccepted()) {
            throw new ConflictHttpException('Panggilan tidak dapat diterima karena status sudah '.$call->status->value.'.');
        }

        DB::transaction(function () use ($call): void {
            $call->update([
                'status' => CallStatus::Accepted,
                'answered_at' => now(),
                'accepted_at' => now(),
            ]);
        });

        $call->refresh()->load(['caller', 'receiver']);

        // Generate receiver's LiveKit credentials
        $connection = $this->tokenService->createAudioTokenCredentials($call, $receiver);

        // Broadcast CallAccepted event to both caller and receiver channels
        broadcast(new CallAccepted($call));

        return [
            'call' => $call,
            'connection' => $connection,
        ];
    }

    /**
     * Reject a ringing call as the receiver.
     */
    public function rejectCall(Call $call, User $receiver): Call
    {
        if (! $call->isReceiver($receiver)) {
            abort(403, 'Hanya penerima panggilan yang dapat menolak panggilan.');
        }

        if (! $call->canBeRejected()) {
            throw new ConflictHttpException('Panggilan tidak dapat ditolak karena status sudah '.$call->status->value.'.');
        }

        DB::transaction(function () use ($call, $receiver): void {
            $call->update([
                'status' => CallStatus::Rejected,
                'end_reason' => CallEndReason::Rejected,
                'ended_at' => now(),
                'ended_by_id' => $receiver->id,
            ]);
        });

        $call->refresh()->load(['caller', 'receiver']);

        // Notify caller and cleanup LiveKit room
        broadcast(new CallRejected($call));
        CleanupCallRoom::dispatch($call->room_name);

        return $call;
    }

    /**
     * Cancel a ringing call as the caller.
     */
    public function cancelCall(Call $call, User $caller): Call
    {
        if (! $call->isCaller($caller)) {
            abort(403, 'Hanya pemanggil yang dapat membatalkan panggilan.');
        }

        if (! $call->canBeCancelled()) {
            throw new ConflictHttpException('Panggilan tidak dapat dibatalkan karena status sudah '.$call->status->value.'.');
        }

        DB::transaction(function () use ($call, $caller): void {
            $call->update([
                'status' => CallStatus::Cancelled,
                'end_reason' => CallEndReason::CallerEnded,
                'ended_at' => now(),
                'ended_by_id' => $caller->id,
            ]);
        });

        $call->refresh()->load(['caller', 'receiver']);

        // Notify receiver and cleanup LiveKit room
        broadcast(new CallCancelled($call));
        CleanupCallRoom::dispatch($call->room_name);

        return $call;
    }

    /**
     * End an active or ringing call as a participant.
     */
    public function endCall(Call $call, User $participant): Call
    {
        if (! $call->isParticipant($participant)) {
            abort(403, 'Anda bukan peserta panggilan ini.');
        }

        if (! $call->canBeEnded()) {
            throw new ConflictHttpException('Panggilan sudah berakhir.');
        }

        $endReason = $call->isCaller($participant)
            ? CallEndReason::CallerEnded
            : CallEndReason::ReceiverEnded;

        // Calculate duration from media start time (when both joined)
        $mediaStart = $call->getMediaStartTime();
        $durationSeconds = $mediaStart !== null
            ? max(0, (int) $mediaStart->diffInSeconds(now()))
            : 0;

        DB::transaction(function () use ($call, $participant, $endReason, $durationSeconds): void {
            $call->update([
                'status' => CallStatus::Ended,
                'end_reason' => $endReason,
                'ended_at' => now(),
                'ended_by_id' => $participant->id,
                'duration_seconds' => $durationSeconds,
            ]);
        });

        $call->refresh()->load(['caller', 'receiver']);

        // Notify both participants and request room cleanup
        broadcast(new CallEnded($call));
        CleanupCallRoom::dispatch($call->room_name);

        return $call;
    }

    /**
     * Get participant token for an authorized participant.
     *
     * @return array{server_url: string, token: string, room_name: string, identity: string, expires_in: int}
     */
    public function getParticipantToken(Call $call, User $participant): array
    {
        if (! $call->isParticipant($participant)) {
            abort(403, 'Anda bukan peserta panggilan ini.');
        }

        if (! $call->isJoinable()) {
            throw new ConflictHttpException('Panggilan sudah tidak aktif.');
        }

        return $this->tokenService->createAudioTokenCredentials($call, $participant);
    }
}
