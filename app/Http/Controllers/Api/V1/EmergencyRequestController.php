<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DispatchStatus;
use App\Enums\EmergencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmergencyRequestRequest;
use App\Http\Resources\EmergencyRequestResource;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmergencyRequestController extends Controller
{
    /**
     * Create a new emergency SOS request and start dispatch matching.
     */
    public function store(StoreEmergencyRequestRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $existing = EmergencyRequest::query()
            ->where('user_id', $userId)
            ->whereIn('status', EmergencyStatus::activeValues())
            ->first();

        if ($existing !== null) {
            return response()->json([
                'message' => 'Anda masih memiliki panggilan darurat yang sedang aktif.',
                'data' => new EmergencyRequestResource($existing),
            ], 409);
        }

        $emergency = EmergencyRequest::create([
            'user_id' => $userId,
            'request_code' => EmergencyRequest::generateRequestCode(),
            'is_for_self' => $request->boolean('is_for_self', true),
            'emergency_type' => $request->input('emergency_type') ?? 'Darurat Umum',
            'severity_level' => $request->input('severity_level') ?? 'critical',
            'patient_notes' => $request->input('patient_notes'),
            'pickup_latitude' => $request->float('pickup_latitude'),
            'pickup_longitude' => $request->float('pickup_longitude'),
            'pickup_address' => $request->input('pickup_address'),
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'request_at' => now(),
        ]);

        // Immediately offer to the nearest dispatchable driver if one is available
        $emergency->offerToNextDriver();

        return (new EmergencyRequestResource($emergency->fresh()))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Get user's current ongoing emergency request.
     */
    public function current(Request $request): JsonResponse
    {
        $emergency = EmergencyRequest::query()
            ->where('user_id', $request->user()->id)
            ->whereIn('status', EmergencyStatus::activeValues())
            ->latest('request_at')
            ->first();

        if ($emergency === null) {
            return response()->json(['data' => null]);
        }

        // If still searching, trigger redispatch/expansion check
        if ($emergency->hasStatus(EmergencyStatus::SearchingDriver) && ! $emergency->dispatchLogs()->pending()->exists()) {
            $emergency->offerToNextDriver();
        }

        return (new EmergencyRequestResource($emergency->fresh()))
            ->response();
    }

    /**
     * Cancel an ongoing emergency request.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var EmergencyRequest $emergency */
        $emergency = EmergencyRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        if (! in_array($emergency->status, [
            EmergencyStatus::SearchingDriver->value,
            EmergencyStatus::DriverAssigned->value,
            EmergencyStatus::EnRouteToPickup->value,
        ], strict: true)) {
            return response()->json([
                'message' => 'Panggilan darurat tidak dapat dibatalkan pada status ini.',
            ], 422);
        }

        $emergency->update([
            'status' => EmergencyStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $validated['reason'] ?? 'Dibatalkan oleh pemanggil',
        ]);

        // Cancel any pending offers for this request
        $emergency->dispatchLogs()->pending()->update([
            'status' => DispatchStatus::Cancelled->value,
            'responded_at' => now(),
        ]);

        return (new EmergencyRequestResource($emergency->fresh()))
            ->response();
    }
}
