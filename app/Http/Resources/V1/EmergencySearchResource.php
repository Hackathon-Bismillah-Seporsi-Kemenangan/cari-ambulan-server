<?php

namespace App\Http\Resources\V1;

use App\Enums\EmergencyStatus;
use App\Models\DispatchLog;
use App\Models\DriverProfile;
use App\Models\EmergencyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The reporter's view of their own request: the search for an ambulance
 * ("Mencari ambulans terdekat…"), then the ambulance on its way and the
 * drive to the hospital.
 *
 * @mixin EmergencyRequest
 */
class EmergencySearchResource extends JsonResource
{
    /**
     * Ambulances shown on the search map, around the pickup point.
     */
    public const NEARBY_RADIUS_KM = 25;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Free ambulances, plus the one whose phone is ringing for this
        // request (no longer "dispatchable", but the one most worth showing).
        $ringing = DriverProfile::query()
            ->whereIn('id', $this->dispatchLogs()->pending()->select('driver_id'))
            ->whereNotNull('current_latitude')
            ->get()
            ->each(fn (DriverProfile $driver) => $driver->distance_km = round(
                DriverProfile::distanceKm($this->pickup_latitude, $this->pickup_longitude, $driver->current_latitude, $driver->current_longitude),
                2,
            ));
        $nearby = DriverProfile::nearestDispatchableTo($this->pickup_latitude, $this->pickup_longitude)
            ->concat($ringing)
            ->where('distance_km', '<=', self::NEARBY_RADIUS_KM)
            ->sortBy('distance_km');

        $this->resource->loadMissing(['driverProfile.user', 'driverProfile.ambulance', 'driverProfile.hospital', 'ambulance', 'hospital']);

        $driver = null;
        $ambulance = null;
        $hospital = null;
        $eta = null;

        if ($this->driver_profile_id !== null) {
            $driverProfile = $this->driverProfile;
            $driverUser = $driverProfile?->user;
            $amb = $this->ambulance ?? $driverProfile?->ambulance;
            // Until the patient is on board, the driver's own hospital.
            $hosp = $this->hospital ?? $driverProfile?->hospital;
            // The reporter follows the ambulance live only while it serves them.
            $tracking = $this->resource->isActiveTrip()
                && $driverProfile?->current_latitude !== null
                && $driverProfile?->current_longitude !== null;

            $driver = [
                'name' => $driverUser?->full_name ?? $driverUser?->name,
                'phone' => $driverUser?->phone,
                'photo_url' => null,
                'rating' => null,
                'trip_count' => null,
            ];

            $ambulance = [
                'plate_number' => $amb?->plate_number,
                'vehicle_type' => $amb?->vehicle_type ?? 'Ambulans',
                'latitude' => $tracking ? (float) $driverProfile->current_latitude : null,
                'longitude' => $tracking ? (float) $driverProfile->current_longitude : null,
                // How old that position is, so the reporter's app can say the
                // driver went quiet (phone off, no signal) instead of showing
                // a frozen ambulance. Server-side age: no clock skew.
                'location_age_seconds' => $tracking && $driverProfile->location_updated_at !== null
                    ? max(0, (int) $driverProfile->location_updated_at->diffInSeconds(now()))
                    : null,
            ];

            // With its location, so the reporter can follow the drive to the
            // IGD like the driver does.
            $hospital = $hosp !== null ? new HospitalResource($hosp) : null;

            // To where the ambulance is heading now: the pickup, then the
            // assigned hospital.
            [$toLatitude, $toLongitude] = match (true) {
                $this->hasStatus(EmergencyStatus::EnRouteToHospital) => [$this->hospital?->latitude, $this->hospital?->longitude],
                $this->hasStatus(EmergencyStatus::DriverAssigned),
                $this->hasStatus(EmergencyStatus::EnRouteToPickup) => [$this->pickup_latitude, $this->pickup_longitude],
                default => [null, null],
            };

            if ($tracking && $toLatitude !== null && $toLongitude !== null) {
                $distanceKm = round(
                    DriverProfile::distanceKm(
                        $toLatitude,
                        $toLongitude,
                        $driverProfile->current_latitude,
                        $driverProfile->current_longitude,
                    ),
                    2,
                );

                $eta = [
                    'minutes' => DispatchLog::drivingMinutes($distanceKm),
                    'distance_km' => $distanceKm,
                ];
            }
        }

        return [
            'id' => $this->id,
            'code' => $this->request_code,
            'status' => $this->status,
            'emergency_type' => $this->emergency_type,
            'pickup' => [
                'latitude' => $this->pickup_latitude,
                'longitude' => $this->pickup_longitude,
                'address' => $this->pickup_address,
            ],
            'drivers_contacted' => $this->dispatchLogs()->count(),
            'search_radius_km' => $this->search_radius_km ?? 3,
            'radius_expands_in_seconds' => $this->radiusExpandsInSeconds(),
            'search_exhausted' => $this->isSearchExhausted(),
            'driver' => $driver,
            'ambulance' => $ambulance,
            'hospital' => $hospital,
            'eta' => $eta,
            // ~100 m precision: enough for the map, without tracking drivers.
            'nearby_ambulances' => $nearby->map(fn (DriverProfile $driver) => [
                'latitude' => round($driver->current_latitude, 3),
                'longitude' => round($driver->current_longitude, 3),
                'distance_km' => $driver->distance_km,
            ])->values(),
            // Only once dispatch has chosen the hospital and alerted its IGD.
            'hospital_preparation' => $this->hospital === null ? null : $this->resource->igdPreparation(),
            'request_at' => $this->request_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
        ];
    }
}

