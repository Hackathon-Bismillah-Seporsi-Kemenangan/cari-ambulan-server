<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\CallStatus;
use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Http\Resources\EmergencyRequestResource;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use App\Models\Hospital;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The driver's side of an emergency request, from offer to IGD handover.
 */
class TripController extends DriverController
{
    /**
     * History of completed and cancelled trips served by this driver.
     */
    public function index(Request $request): JsonResponse
    {
        $driver = $this->driver($request);
        $trips = $driver->emergencyRequests()
            ->with(['user', 'hospital'])
            ->whereIn('status', [EmergencyStatus::Completed->value, EmergencyStatus::Cancelled->value])
            ->latest()
            ->limit(50)
            ->get();

        $data = $trips->map(function (EmergencyRequest $trip) {
            return [
                'id' => (string) $trip->id,
                'code' => $trip->request_code ?? '–',
                'status' => $trip->status instanceof EmergencyStatus ? $trip->status->value : (string) $trip->status,
                'emergency_type' => $trip->emergency_type,
                'pickup_address' => $trip->pickup_address,
                'request_at' => $trip->created_at?->toIso8601String(),
                'patient_name' => $trip->user?->full_name ?? $trip->user?->name,
                'hospital_name' => $trip->hospital?->name,
                'completed_at' => $trip->completed_at?->toIso8601String(),
                'cancelled_at' => $trip->cancelled_at?->toIso8601String(),
                'cancel_reason' => $trip->cancel_reason,
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Get the active emergency trip being served by this driver.
     */
    public function current(Request $request): EmergencyRequestResource|JsonResponse
    {
        $driver = $this->driver($request);
        $trip = $driver->activeTrip();

        if ($trip === null) {
            $trip = $driver->emergencyRequests()
                ->where('status', EmergencyStatus::Cancelled->value)
                ->where('cancelled_at', '>=', now()->subMinutes(30))
                ->latest('cancelled_at')
                ->first();
        }

        return $trip === null
            ? response()->json(['data' => null])
            : new EmergencyRequestResource($trip->load(['user.medicalProfile', 'hospital']));
    }

    /**
     * Alias for current.
     */
    public function show(Request $request): EmergencyRequestResource|JsonResponse
    {
        return $this->current($request);
    }

    public function accept(Request $request, EmergencyRequest $emergencyRequest): EmergencyRequestResource
    {
        $driver = $this->driver($request);

        $accepted = DB::transaction(function () use ($driver, $emergencyRequest): EmergencyRequest {
            /** @var EmergencyRequest $emergency */
            $emergency = EmergencyRequest::query()->lockForUpdate()->findOrFail($emergencyRequest->id);
            $offer = $emergency->dispatchLogs()->where('driver_id', $driver->id)->latest('offered_at')->first();

            abort_if($offer === null, 404);
            abort_if($driver->activeTrip() !== null, 409, 'Anda masih memiliki order yang berjalan.');
            abort_unless(
                $emergency->hasStatus(EmergencyStatus::SearchingDriver),
                409,
                'Order sudah diambil driver lain atau dibatalkan.',
            );
            abort_unless($offer->isPending(), 409, 'Waktu untuk menerima order sudah habis.');
            abort_if($driver->ambulance === null, 422, 'Akun driver belum terhubung ke ambulans.');

            $offer->update([
                'status' => DispatchStatus::Accepted->value,
                'responded_at' => now(),
            ]);
            $emergency->update([
                'driver_profile_id' => $driver->id,
                'ambulance_id' => $driver->ambulance->id,
                'status' => EmergencyStatus::EnRouteToPickup->value,
                'accepted_at' => now(),
            ]);
            $driver->update(['availability_status' => DriverAvailability::Busy->value]);

            // Other drivers ringing for this request stop ringing.
            $emergency->dispatchLogs()->pending()->where('id', '!=', $offer->id)->update([
                'status' => DispatchStatus::Timeout->value,
                'responded_at' => now(),
            ]);

            return $emergency;
        });

        return new EmergencyRequestResource($accepted->load(['user.medicalProfile', 'hospital']));
    }

    public function decline(Request $request, EmergencyRequest $emergencyRequest): Response
    {
        $driver = $this->driver($request);
        $offer = $emergencyRequest->dispatchLogs()
            ->where('driver_id', $driver->id)
            ->latest('offered_at')
            ->first();

        abort_if($offer === null, 404);

        if ($offer->isPending()) {
            $offer->update([
                'status' => DispatchStatus::Declined->value,
                'responded_at' => now(),
            ]);
            $emergencyRequest->offerToNextDriver();
        }

        return response()->noContent();
    }

    public function arrive(Request $request, EmergencyRequest $emergencyRequest): EmergencyRequestResource
    {
        $this->transition(
            $request,
            $emergencyRequest,
            from: [EmergencyStatus::DriverAssigned, EmergencyStatus::EnRouteToPickup],
            to: EmergencyStatus::ArriveAtPickup,
            attributes: ['arrived_at_pickup_at' => now()],
        );

        return new EmergencyRequestResource($emergencyRequest->load(['user.medicalProfile', 'hospital']));
    }

    /**
     * Patient on board: assigns the nearest hospital (unless one was already chosen).
     */
    public function startToHospital(Request $request, EmergencyRequest $emergencyRequest): EmergencyRequestResource
    {
        $driver = $this->driver($request);
        $this->ensureAssigned($emergencyRequest, $driver);

        $hospital = $emergencyRequest->hospital ?? Hospital::nearestTo(
            $driver->current_latitude ?? $emergencyRequest->pickup_latitude,
            $driver->current_longitude ?? $emergencyRequest->pickup_longitude,
        );

        abort_if($hospital === null, 422, 'Belum ada rumah sakit dengan lokasi terdaftar.');

        $this->transition(
            $request,
            $emergencyRequest,
            from: [EmergencyStatus::ArriveAtPickup],
            to: EmergencyStatus::EnRouteToHospital,
            attributes: ['hospital_id' => $hospital->id, 'picked_up_at' => now()],
        );

        $emergencyRequest->load('user.medicalProfile');
        $emergencyRequest->setRelation('hospital', $hospital);

        return new EmergencyRequestResource($emergencyRequest);
    }

    /**
     * Patient handed over to the IGD; the driver is back online.
     */
    public function complete(Request $request, EmergencyRequest $emergencyRequest): EmergencyRequestResource
    {
        $this->transition(
            $request,
            $emergencyRequest,
            from: [EmergencyStatus::EnRouteToHospital],
            to: EmergencyStatus::Completed,
            attributes: ['completed_at' => now()],
        );

        // The order is closed, so is any call about it.
        $emergencyRequest->calls()->live()->update([
            'status' => CallStatus::Ended->value,
            'ended_at' => now(),
        ]);

        $this->driver($request)->update(['availability_status' => DriverAvailability::Online->value]);
        EmergencyRequest::redispatchWaiting();

        return new EmergencyRequestResource($emergencyRequest->load(['user.medicalProfile', 'hospital']));
    }

    /**
     * Advance the emergency trip to the next status via single status payload.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:en_route_to_pickup,arrive_at_pickup,en_route_to_hospital,completed',
            ],
        ]);

        $driver = $this->driver($request);
        $trip = $driver->activeTrip();

        if ($trip === null) {
            return response()->json([
                'message' => 'Tidak ada perjalanan darurat yang sedang aktif.',
            ], 404);
        }

        $targetStatus = EmergencyStatus::from($validated['status']);

        if (! $trip->progressTripStatus($targetStatus)) {
            return response()->json([
                'message' => "Transisi status ke '{$targetStatus->value}' tidak diizinkan dari status saat ini '{$trip->status}'.",
            ], 422);
        }

        return response()->json([
            'message' => 'Status perjalanan darurat berhasil diperbarui.',
            'data' => new EmergencyRequestResource($trip->fresh()),
        ]);
    }

    /**
     * Moves the driver's own request to the next status, refusing skips.
     *
     * @param  list<EmergencyStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    private function transition(
        Request $request,
        EmergencyRequest $emergency,
        array $from,
        EmergencyStatus $to,
        array $attributes,
    ): void {
        $this->ensureAssigned($emergency, $this->driver($request));

        $allowed = array_map(fn (EmergencyStatus $status) => $status->value, $from);
        abort_unless(
            in_array($emergency->status, $allowed, strict: true),
            409,
            'Status order tidak sesuai untuk langkah ini.',
        );

        $emergency->update(['status' => $to->value, ...$attributes]);
    }

    private function ensureAssigned(EmergencyRequest $emergency, DriverProfile $driver): void
    {
        abort_unless($emergency->driver_profile_id === $driver->id, 404);
    }
}
