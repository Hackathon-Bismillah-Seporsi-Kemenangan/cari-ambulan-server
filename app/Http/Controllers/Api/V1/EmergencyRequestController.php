<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEmergencyRequestRequest;
use App\Http\Resources\V1\EmergencyHistoryResource;
use App\Http\Resources\V1\EmergencySearchResource;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

/**
 * Emergency calls placed by civilians (the user app).
 */
class EmergencyRequestController extends Controller
{
    /**
     * Past emergency requests of the authenticated user ("Riwayat").
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = $request->user()->emergencyRequests()
            ->with([
                'hospital',
                'ambulance',
                'driverProfile.user',
                'driverProfile.hospital',
                'driverProfile.ambulance',
            ])
            ->latest('request_at')
            ->limit(50)
            ->get();

        return EmergencyHistoryResource::collection($requests);
    }

    /**
     * Create a new emergency SOS request and start dispatch matching.
     */
    public function store(StoreEmergencyRequestRequest $request): JsonResponse
    {
        $existing = $request->user()->emergencyRequests()->open()->first();

        if ($existing !== null) {
            return response()->json([
                'message' => 'Anda masih memiliki permintaan ambulans yang berjalan.',
                'data' => new EmergencySearchResource($existing),
            ], 409);
        }

        $emergency = $request->user()->emergencyRequests()->create([
            ...$request->validated(),
            'request_code' => EmergencyRequest::generateRequestCode(),
            'status' => EmergencyStatus::SearchingDriver->value,
            'search_radius_km' => 3,
            'radius_changed_at' => now(),
            'request_at' => now(),
        ]);

        $emergency->offerToNextDriver();

        return (new EmergencySearchResource($emergency->load('user.medicalProfile')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The reporter's request that is still searching or under way, so the
     * app can reopen it after a restart.
     */
    public function current(Request $request): EmergencySearchResource|JsonResponse
    {
        $emergency = $request->user()->emergencyRequests()->open()->latest('request_at')->first();

        return $emergency === null
            ? response()->json(['data' => null])
            : new EmergencySearchResource($emergency);
    }

    /**
     * The reporter's own request; the app polls this while searching.
     */
    public function show(Request $request, EmergencyRequest $emergencyRequest): EmergencySearchResource
    {
        abort_if($emergencyRequest->user_id !== $request->user()->id, 404);

        return new EmergencySearchResource($emergencyRequest->refresh());
    }

    /**
     * Cancels an emergency request before or after driver accepts, with an optional reason.
     * Allowed during searching, driver_assigned, or en_route_to_pickup.
     */
    public function cancel(Request $request, EmergencyRequest $emergencyRequest): EmergencySearchResource
    {
        abort_if($emergencyRequest->user_id !== $request->user()->id, 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
            'cancel_reason' => ['nullable', 'string', 'max:255'],
        ]);
        $reason = $validated['reason'] ?? $validated['cancel_reason'] ?? 'Dibatalkan oleh pemanggil';

        $cancelled = DB::transaction(function () use ($emergencyRequest, $reason): EmergencyRequest {
            /** @var EmergencyRequest $emergency */
            $emergency = EmergencyRequest::query()->lockForUpdate()->findOrFail($emergencyRequest->id);

            $cancellableStatuses = [
                EmergencyStatus::SearchingDriver->value,
                EmergencyStatus::DriverAssigned->value,
                EmergencyStatus::EnRouteToPickup->value,
            ];

            abort_unless(
                in_array($emergency->status, $cancellableStatuses, strict: true),
                409,
                'Ambulans sudah tiba di lokasi atau pesanan telah selesai, tidak dapat dibatalkan.',
            );

            $emergency->update([
                'status' => EmergencyStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            // Stop the driver's phone ringing and free them for other calls.
            $emergency->dispatchLogs()->pending()->update([
                'status' => DispatchStatus::Timeout->value,
                'responded_at' => now(),
            ]);

            // Free the assigned driver so they can take other calls.
            if ($emergency->driver_profile_id !== null) {
                $driver = DriverProfile::query()->lockForUpdate()->find($emergency->driver_profile_id);
                $driver?->update(['availability_status' => DriverAvailability::Online->value]);
            }

            return $emergency;
        });

        return new EmergencySearchResource($cancelled->refresh());
    }

    /**
     * Widens the search radius right away (moves to next step in 3 -> 5 -> 10 -> 15 -> 25 km).
     */
    public function widenRadius(Request $request, EmergencyRequest $emergencyRequest): EmergencySearchResource
    {
        abort_if($emergencyRequest->user_id !== $request->user()->id, 404);
        abort_unless(
            $emergencyRequest->hasStatus(EmergencyStatus::SearchingDriver),
            409,
            'Hanya pencarian aktif yang dapat memperluas radius.',
        );

        if (($emergencyRequest->search_radius_km ?? 3) < 25) {
            $emergencyRequest->widenRadius();
        }

        $emergencyRequest->offerToNextDriver();

        return new EmergencySearchResource($emergencyRequest->refresh());
    }

    /**
     * "Cari Lagi" button: restarts search at 25 km and offers again to drivers who declined.
     */
    public function retrySearch(Request $request, EmergencyRequest $emergencyRequest): EmergencySearchResource
    {
        abort_if($emergencyRequest->user_id !== $request->user()->id, 404);
        abort_unless(
            $emergencyRequest->hasStatus(EmergencyStatus::SearchingDriver),
            409,
            'Hanya pencarian aktif yang dapat diulang.',
        );

        $emergencyRequest->update([
            'search_radius_km' => 25,
            'radius_changed_at' => now(),
            'search_exhausted_at' => null,
        ]);

        $emergencyRequest->dispatchLogs()
            ->whereIn('status', [DispatchStatus::Declined->value, DispatchStatus::Timeout->value])
            ->delete();

        $emergencyRequest->offerToNextDriver();

        return new EmergencySearchResource($emergencyRequest->refresh());
    }
}
