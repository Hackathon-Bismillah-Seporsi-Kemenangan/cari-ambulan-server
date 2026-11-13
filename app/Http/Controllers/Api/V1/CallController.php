<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Call\StartCallRequest;
use App\Http\Resources\CallResource;
use App\Http\Resources\V1\TripCallResource;
use App\Models\Call;
use App\Models\TripCall;
use App\Models\User;
use App\Services\Call\CallLifecycleService;
use App\Services\Call\CallService;
use App\Services\Call\LiveKitTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 1-on-1 audio call management powered by LiveKit Cloud and FCM.
 */
class CallController extends Controller
{
    /**
     * List paginated call history for the authenticated user.
     */
    public function index(Request $request, CallService $callService): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $filters = $request->only(['status', 'date_from', 'date_to']);
        $paginator = $callService->getCallHistory($user, $filters, (int) $request->input('per_page', 15));

        return CallResource::collection($paginator);
    }

    /**
     * Start a new 1-on-1 audio call to another user.
     */
    public function store(StartCallRequest $request, CallLifecycleService $lifecycleService): JsonResponse
    {
        /** @var User $caller */
        $caller = $request->user();
        $receiverId = $request->getReceiverId();
        $options = $request->only(['call_type', 'metadata']);

        $result = $lifecycleService->startCall($caller, $receiverId, $options);

        return response()->json([
            'data' => (new CallResource($result['call']))->resolve(),
            'connection' => $result['connection'],
            // Top-level aliases for flexible client consumption
            'token' => $result['connection']['token'],
            'url' => $result['connection']['server_url'],
            'room_name' => $result['connection']['room_name'],
        ], 201);
    }

    /**
     * Retrieve details of a specific call.
     */
    public function show(Request $request, string $call, CallService $callService): JsonResponse|TripCallResource
    {
        $callModel = Call::find($call);

        // Fallback for legacy TripCall
        if (! $callModel) {
            $tripCall = TripCall::find($call);
            if ($tripCall) {
                return app(CallSignalingController::class)->show($request, $tripCall);
            }

            abort(404, 'Panggilan tidak ditemukan.');
        }

        abort_unless($callModel->isParticipant($request->user()), 403, 'Anda bukan peserta panggilan ini.');

        $callModel->loadMissing(['caller', 'receiver']);

        return (new CallResource($callModel))->response();
    }

    /**
     * Accept a ringing call as the receiver.
     */
    public function accept(Request $request, Call $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $result = $lifecycleService->acceptCall($call, $request->user());

        return response()->json([
            'data' => (new CallResource($result['call']))->resolve(),
            'connection' => $result['connection'],
            'token' => $result['connection']['token'],
            'url' => $result['connection']['server_url'],
            'room_name' => $result['connection']['room_name'],
        ]);
    }

    /**
     * Reject a ringing call as the receiver.
     */
    public function reject(Request $request, Call $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $call = $lifecycleService->rejectCall($call, $request->user());

        return (new CallResource($call))->response();
    }

    /**
     * Cancel a ringing call as the caller.
     */
    public function cancel(Request $request, Call $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $call = $lifecycleService->cancelCall($call, $request->user());

        return (new CallResource($call))->response();
    }

    /**
     * End an active or ringing call as a participant.
     */
    public function end(Request $request, string $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $callModel = Call::find($call);

        // Fallback for legacy TripCall
        if (! $callModel) {
            $tripCall = TripCall::find($call);
            if ($tripCall) {
                return app(CallSignalingController::class)->end($request, $tripCall);
            }

            abort(404, 'Panggilan tidak ditemukan.');
        }

        $callModel = $lifecycleService->endCall($callModel, $request->user());

        return (new CallResource($callModel))->response();
    }

    /**
     * Generate LiveKit participant token.
     */
    public function token(Request $request, string $call, CallLifecycleService $lifecycleService): JsonResponse
    {
        $callModel = Call::find($call);

        if (! $callModel) {
            $tripCall = TripCall::find($call);
            if ($tripCall) {
                $user = $request->user();
                $emergency = $tripCall->emergencyRequest;
                $isCaller = (string) $tripCall->caller_id === (string) $user->id;
                // The order's owner is the other party when the driver is the one calling.
                $isPatient = $emergency && (string) $emergency->user_id === (string) $user->id;
                $isDriver = $emergency && $emergency->driverProfile && (string) $emergency->driverProfile->user_id === (string) $user->id;
                abort_unless($isCaller || $isPatient || $isDriver, 403, 'Anda bukan peserta panggilan ini.');

                if (in_array($tripCall->status, [CallStatus::Ended->value, CallStatus::Declined->value, CallStatus::Missed->value], true)) {
                    abort(409, 'Panggilan sudah tidak aktif.');
                }

                $tokenService = app(LiveKitTokenService::class);
                $fakeCall = new Call(['room_name' => $tripCall->room_name ?? (string) $tripCall->id]);
                $credentials = $tokenService->createAudioTokenCredentials($fakeCall, $user);

                return response()->json([
                    'token' => $credentials['token'],
                    'url' => $credentials['server_url'],
                    'room_name' => $credentials['room_name'],
                    'identity' => $credentials['identity'],
                    'expires_in' => $credentials['expires_in'],
                    'server_url' => $credentials['server_url'],
                ]);
            }

            abort(404, 'Panggilan tidak ditemukan.');
        }

        $credentials = $lifecycleService->getParticipantToken($callModel, $request->user());

        return response()->json(array_merge($credentials, [
            'url' => $credentials['server_url'],
        ]));
    }
}
