<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use App\Events\Call\CallEnded;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CallParticipant;
use App\Models\ProcessedWebhook;
use App\Services\Call\LiveKitTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Handle incoming webhooks from LiveKit Cloud with signature verification and idempotency.
 */
class LiveKitWebhookController extends Controller
{
    public function handle(Request $request, LiveKitTokenService $tokenService): JsonResponse
    {
        $authHeader = $request->header('Authorization', '');
        $rawBody = $request->getContent();

        // 1. Verify webhook signature
        if (! $tokenService->verifyWebhookSignature($rawBody, $authHeader)) {
            Log::warning('LiveKit webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = json_decode($rawBody, true) ?? [];
        $eventId = $payload['id'] ?? $request->header('Livekit-Webhook-Id') ?? hash('sha256', $rawBody);
        $eventType = $payload['event'] ?? 'unknown';
        $roomName = $payload['room']['name'] ?? null;

        // 2. Prevent duplicate event processing (idempotency)
        if (ProcessedWebhook::where('event_id', $eventId)->exists()) {
            return response()->json([
                'status' => 'already_processed',
                'event_id' => $eventId,
            ]);
        }

        DB::transaction(function () use ($eventId, $eventType): void {
            ProcessedWebhook::create([
                'event_id' => $eventId,
                'event_type' => $eventType,
                'processed_at' => now(),
            ]);
        });

        Log::info("LiveKit webhook received: {$eventType}", [
            'event_id' => $eventId,
            'room' => $roomName,
        ]);

        if (empty($roomName)) {
            return response()->json(['status' => 'handled', 'event' => $eventType]);
        }

        $call = Call::where('room_name', $roomName)->first();

        if (! $call) {
            return response()->json(['status' => 'ignored_unknown_room', 'room' => $roomName]);
        }

        // 3. Process events safely without overwriting terminal statuses
        switch ($eventType) {
            case 'participant_joined':
                $this->handleParticipantJoined($call, $payload['participant'] ?? []);
                break;

            case 'participant_left':
                $this->handleParticipantLeft($call, $payload['participant'] ?? []);
                break;

            case 'room_finished':
                $this->handleRoomFinished($call, $payload['room'] ?? []);
                break;

            case 'room_started':
            default:
                break;
        }

        return response()->json([
            'status' => 'handled',
            'event' => $eventType,
        ]);
    }

    /**
     * Handle participant joined event.
     *
     * @param  array<string, mixed>  $participantData
     */
    protected function handleParticipantJoined(Call $call, array $participantData): void
    {
        $identity = $participantData['identity'] ?? null;

        if (! $identity) {
            return;
        }

        CallParticipant::updateOrCreate(
            ['call_id' => $call->id, 'livekit_identity' => $identity],
            [
                'user_id' => $identity,
                'joined_at' => now(),
            ]
        );

        // If call is still ringing or initiated, check if both participants have now joined
        if (! $call->status->isTerminal()) {
            $joinedCount = $call->participants()->whereNotNull('joined_at')->count();
            if ($joinedCount >= 2 && $call->status !== CallStatus::Accepted) {
                $call->update([
                    'status' => CallStatus::Accepted,
                    'answered_at' => $call->answered_at ?? now(),
                ]);
            }
        }
    }

    /**
     * Handle participant left event.
     *
     * @param  array<string, mixed>  $participantData
     */
    protected function handleParticipantLeft(Call $call, array $participantData): void
    {
        $identity = $participantData['identity'] ?? null;

        if (! $identity) {
            return;
        }

        $participant = CallParticipant::where('call_id', $call->id)
            ->where('livekit_identity', $identity)
            ->first();

        if ($participant && ! $participant->left_at) {
            $participant->update(['left_at' => now()]);
        }
    }

    /**
     * Handle room finished event.
     *
     * @param  array<string, mixed>  $roomData
     */
    protected function handleRoomFinished(Call $call, array $roomData): void
    {
        // Prevent terminal statuses from being overwritten by delayed events
        if ($call->status->isTerminal()) {
            return;
        }

        $mediaStart = $call->getMediaStartTime();
        $roomDuration = isset($roomData['duration']) ? (int) $roomData['duration'] : null;

        $duration = $mediaStart !== null
            ? max(0, (int) $mediaStart->diffInSeconds(now()))
            : ($roomDuration ?? 0);

        $call->update([
            'status' => CallStatus::Ended,
            'end_reason' => $call->end_reason ?? CallEndReason::ConnectionLost,
            'ended_at' => now(),
            'duration_seconds' => $duration,
        ]);

        $call->load(['caller', 'receiver']);
        broadcast(new CallEnded($call));
    }
}
