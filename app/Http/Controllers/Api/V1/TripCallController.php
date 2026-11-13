<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CallStatus;
use App\Events\CallStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\TripCallResource;
use App\Models\EmergencyRequest;
use App\Models\TripCall;
use App\Services\Notification\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Voice calls between the reporter (user app) and the assigned driver.
 */
class TripCallController extends Controller
{
    public function store(Request $request, EmergencyRequest $emergencyRequest): JsonResponse
    {
        $user = $request->user();
        $isPatient = (string) $emergencyRequest->user_id === (string) $user->id;
        $isDriver = $emergencyRequest->driverProfile && (string) $emergencyRequest->driverProfile->user_id === (string) $user->id;

        // Like the other order endpoints, other people's orders don't exist.
        abort_unless($isPatient || $isDriver, 404, 'Order tidak ditemukan.');
        abort_unless(
            $emergencyRequest->isActiveTrip(),
            409,
            'Panggilan hanya bisa dilakukan saat ambulans sedang bertugas.',
        );

        $reused = false;

        $call = DB::transaction(function () use ($user, $emergencyRequest, &$reused): TripCall {
            EmergencyRequest::query()->lockForUpdate()->find($emergencyRequest->id);
            TripCall::markMissedForTrip($emergencyRequest);

            foreach ($emergencyRequest->calls()->live()->get() as $live) {
                if ((string) $live->caller_id !== (string) $user->id) {
                    // The other side is calling right now: answer it instead
                    // of dialing back (the app takes the id from the body).
                    abort(response()->json([
                        'message' => 'Lawan bicara sedang menelepon Anda.',
                        'incoming_call_id' => $live->id,
                    ], 409));
                }

                if ($live->status === CallStatus::Ringing->value) {
                    // A repeated tap or a retried request: same call.
                    $reused = true;

                    return $live;
                }

                // An answered call of mine that never got hung up (app killed
                // mid-call): it is dead, start a fresh one.
                $live->update(['status' => CallStatus::Ended->value, 'ended_at' => now()]);
            }

            return $emergencyRequest->calls()->create([
                'caller_id' => $user->id,
                'status' => CallStatus::Ringing->value,
                'rang_at' => now(),
            ]);
        });

        if ($reused) {
            return (new TripCallResource($call->load('caller', 'emergencyRequest')))
                ->response()
                ->setStatusCode(200);
        }

        CallStatusUpdated::broadcastSafely($call, CallStatus::Ringing);

        // Ring the other phone even when its app is closed. Runs after the
        // response, so the caller never waits on Google.
        defer(fn () => app(FcmService::class)->sendTripCall($call));

        return (new TripCallResource($call->load('caller', 'emergencyRequest')))
            ->response()
            ->setStatusCode(201);
    }
}
