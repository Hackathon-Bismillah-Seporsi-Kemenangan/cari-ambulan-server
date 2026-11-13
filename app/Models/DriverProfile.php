<?php

namespace App\Models;

use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Concerns\CalculatesDistance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'license_number',
    'availability_status',
    'current_latitude',
    'current_longitude',
    'location_updated_at',
    'user_id',
    'hospital_id',
])]
class DriverProfile extends Model
{
    use CalculatesDistance, HasFactory, HasUuids;

    public const LOCATION_FRESH_MINUTES = 10;

    protected $attributes = [
        'availability_status' => 'offline',
    ];

    protected function casts(): array
    {
        return [
            'availability_status' => DriverAvailability::class,
            'current_latitude' => 'float',
            'current_longitude' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class, 'hospital_id');
    }

    public function ambulance(): HasOne
    {
        return $this->hasOne(Ambulance::class, 'driver_profile_id');
    }

    public function emergencyRequests(): HasMany
    {
        return $this->hasMany(EmergencyRequest::class, 'driver_profile_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class, 'driver_id');
    }

    /**
     * Online drivers with an ambulance and recent location beacon.
     *
     * @param  Builder<self>  $query
     */
    public function scopeDispatchable(Builder $query): void
    {
        $query->where('availability_status', DriverAvailability::Online->value)
            ->whereNotNull('current_latitude')
            ->whereNotNull('current_longitude')
            ->where('location_updated_at', '>=', now()->subMinutes(self::LOCATION_FRESH_MINUTES))
            ->whereHas('ambulance')
            ->whereDoesntHave('dispatchLogs', fn (Builder $offers) => $offers->pending());
    }

    /**
     * Dispatchable drivers sorted by distance, with `distance_km` set.
     *
     * @param  list<string>  $excludedDriverIds
     * @return Collection<int, self>
     */
    public static function nearestDispatchableTo(float $latitude, float $longitude, array $excludedDriverIds = [], ?float $radiusKm = null): Collection
    {
        $query = self::query()
            ->dispatchable()
            ->whereNotIn('id', $excludedDriverIds);

        // Narrow to a bounding box in SQL so only nearby drivers are loaded
        // into PHP; the exact distance is still computed below.
        if ($radiusKm !== null) {
            $latDelta = $radiusKm / 111.0;
            $lngDelta = $radiusKm / (111.0 * max(0.01, cos(deg2rad($latitude))));
            $query->whereBetween('current_latitude', [$latitude - $latDelta, $latitude + $latDelta])
                ->whereBetween('current_longitude', [$longitude - $lngDelta, $longitude + $lngDelta]);
        }

        return $query
            ->get()
            ->each(fn (self $driver) => $driver->distance_km = round(
                self::distanceKm($latitude, $longitude, $driver->current_latitude, $driver->current_longitude),
                2,
            ))
            ->sortBy('distance_km')
            ->values();
    }

    /**
     * The emergency request this driver is actively serving, if any.
     */
    public function activeTrip(): ?EmergencyRequest
    {
        return $this->emergencyRequests()
            ->whereIn(
                'status',
                array_map(fn (EmergencyStatus $status) => $status->value, EmergencyStatus::activeTrip()),
            )
            ->latest('accepted_at')
            ->first();
    }
}
