<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallStatus;
use App\Events\CallSignalEvent;
use App\Events\CallStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TripCallResource;
use App\Models\TripCall;
use App\Services\TripCallStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallSignalingController extends Controller
{
    public function __construct(protected TripCallStateStore $stateStore) {}

    /**
     * The call's current state, polled by both apps so a dropped WebSocket
     * can't leave one side ringing forever.
     */
    public function show(Request $request, TripCall $tripCall): TripCallResource
    {
        $this->ensureParticipant($request, $tripCall);

        $tripCall->markMissedIfExpired();

        return new TripCallResource($tripCall->refresh()->load('caller', 'emergencyRequest'));
    }

    /**
     * A call ringing for the signed-in user (the other side of one of their
     * orders placed it), or null. The reporter's app polls this during a
     * trip so the driver can call them.
     */
    public function incoming(Request $request): TripCallResource|JsonResponse
    {
        $user = $request->user();
        $snapshot = $this->stateStore->findForUser((string) $user->id);

        if ($snapshot === null
            || $snapshot['status'] !== CallStatus::Ringing->value
            || (string) $snapshot['caller_id'] === (string) $user->id) {
            return response()->json(['data' => null]);
        }

        $call = TripCall::with('caller', 'emergencyRequest')->find($snapshot['id']);

        if ($call === null || ! $call->emergencyRequest?->isActiveTrip()) {
            return response()->json(['data' => null]);
        }

        return new TripCallResource($call);
    }

    /**
     * The callee picks up; the caller then gets "answered".
     */
    public function answer(Request $request, TripCall $tripCall): TripCallResource
    {
        $this->ensureCallee($request, $tripCall);
        abort_unless($tripCall->isRinging(), 409, 'Panggilan sudah berakhir.');

        $tripCall->update([
            'status' => CallStatus::Answered->value,
            'answered_at' => now(),
        ]);

        CallStatusUpdated::broadcastSafely($tripCall, CallStatus::Answered);

        return new TripCallResource($tripCall->load('caller', 'emergencyRequest'));
    }

    public function decline(Request $request, TripCall $tripCall): JsonResponse
    {
        $this->ensureCallee($request, $tripCall);

        // A call that already stopped ringing needs no update.
        if ($tripCall->isRinging()) {
            $tripCall->update([
                'status' => CallStatus::Declined->value,
                'ended_at' => now(),
            ]);

            CallStatusUpdated::broadcastSafely($tripCall, CallStatus::Declined);
        }

        return response()->json(['success' => true]);
    }

    /**
     * STUN and TURN servers for this call's WebRTC connection.
     */
    public function iceServers(Request $request, TripCall $tripCall): JsonResponse
    {
        $this->ensureParticipant($request, $tripCall);

        $servers = [
            [
                'urls' => [
                    'stun:stun.l.google.com:19302',
                    'stun:stun1.l.google.com:19302',
                    'stun:stun2.l.google.com:19302',
                ],
            ],
        ];

        $secret = config('services.turn.secret');
        if (filled($secret)) {
            $host = config('services.turn.host') ?: $request->getHost();
            $port = config('services.turn.port', 3478);
            $username = (now()->getTimestamp() + config('services.turn.ttl', 3600)).':'.$request->user()->id;

            $servers[] = [
                'urls' => ["turn:{$host}:{$port}?transport=udp", "turn:{$host}:{$port}?transport=tcp"],
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $secret, true)),
            ];
        }

        return response()->json(['data' => ['ice_servers' => $servers]]);
    }

    public function signal(Request $request, TripCall $tripCall): JsonResponse
    {
        $this->ensureParticipant($request, $tripCall);
        abort_if(
            $tripCall->hasStatus(CallStatus::Ended) ||
            $tripCall->hasStatus(CallStatus::Declined) ||
            $tripCall->hasStatus(CallStatus::Missed),
            409,
            'Panggilan sudah berakhir.'
        );

        $validated = $request->validate([
            // "ready": the callee is subscribed and waits for the offer.
            'type' => 'required|string|in:ready,offer,answer,candidate',
            'data' => 'required',
        ]);

        broadcast(new CallSignalEvent(
            callId: (string) $tripCall->id,
            senderId: (string) $request->user()->id,
            type: $validated['type'],
            data: $validated['data'],
        ))->toOthers();

        return response()->json(['success' => true]);
    }

    public function end(Request $request, TripCall $tripCall): JsonResponse
    {
        $this->ensureParticipant($request, $tripCall);

        if (! $tripCall->hasStatus(CallStatus::Ended)) {
            $tripCall->update([
                'status' => CallStatus::Ended->value,
                'ended_at' => now(),
            ]);

            CallStatusUpdated::broadcastSafely($tripCall, CallStatus::Ended);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Only the other party picks up or declines: the caller can't answer
     * their own call.
     */
    private function ensureCallee(Request $request, TripCall $tripCall): void
    {
        $this->ensureParticipant($request, $tripCall);
        abort_if((string) $tripCall->caller_id === (string) $request->user()->id, 403, 'Anda yang memulai panggilan ini.');
    }

    /**
     * Only the order's reporter and its assigned driver take part, whoever
     * of the two placed the call.
     */
    private function ensureParticipant(Request $request, TripCall $tripCall): void
    {
        $user = $request->user();
        $emergency = $tripCall->emergencyRequest;

        $isReporter = $emergency && (string) $emergency->user_id === (string) $user->id;
        $isDriver = $emergency && $emergency->driverProfile && (string) $emergency->driverProfile->user_id === (string) $user->id;

        abort_unless($isReporter || $isDriver, 403, 'Anda bukan peserta panggilan ini.');
    }
}

