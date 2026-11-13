<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\CallStatus;
use App\Events\CallStatusUpdated;
use App\Http\Resources\V1\TripCallResource;
use App\Models\TripCall;
use App\Services\TripCallStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Voice calls from the reporter during the driver's active order.
 */
class CallController extends DriverController
{
    public function __construct(protected TripCallStateStore $stateStore) {}

    /**
     * The reporter's call ringing or in progress on the active trip, if any.
     * The app polls this during a trip. Calls the driver placed are left
     * out: they would show up as an incoming call from the driver himself.
     */
    public function current(Request $request): TripCallResource|JsonResponse
    {
        $trip = $this->driver($request)->activeTrip();

        if ($trip === null) {
            return response()->json(['data' => null]);
        }

        TripCall::markMissedForTrip($trip);

        $snapshot = $this->stateStore->findForUser((string) $request->user()->id);

        if ($snapshot === null || (string) $snapshot['caller_id'] === (string) $request->user()->id) {
            return response()->json(['data' => null]);
        }

        $call = TripCall::with('caller', 'emergencyRequest')->find($snapshot['id']);

        return $call === null
            ? response()->json(['data' => null])
            : new TripCallResource($call);
    }

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

    public function decline(Request $request, TripCall $tripCall): Response
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

        return response()->noContent();
    }

    public function end(Request $request, TripCall $tripCall): Response
    {
        $this->ensureCallee($request, $tripCall);

        if ($tripCall->hasStatus(CallStatus::Answered)) {
            $tripCall->update([
                'status' => CallStatus::Ended->value,
                'ended_at' => now(),
            ]);

            CallStatusUpdated::broadcastSafely($tripCall, CallStatus::Ended);
        }

        return response()->noContent();
    }

    /**
     * Hides calls on other drivers' orders behind a 404.
     */
    private function ensureCallee(Request $request, TripCall $call): void
    {
        $this->ensureAssigned($call->emergencyRequest, $this->driver($request));
    }
}

