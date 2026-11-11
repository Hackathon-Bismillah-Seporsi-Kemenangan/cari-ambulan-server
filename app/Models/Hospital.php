<?php

namespace App\Models;

use App\Models\Concerns\CalculatesDistance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'address', 'emergency_phone', 'latitude', 'longitude', 'ambulance_entrance', 'admin_user_id'])]
class Hospital extends Model
{
    use CalculatesDistance, HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function driverProfiles(): HasMany
    {
        return $this->hasMany(DriverProfile::class, 'hospital_id');
    }

    public function ambulances(): HasMany
    {
        return $this->hasMany(Ambulance::class, 'hospital_id');
    }

    /**
     * Closest hospital with known coordinates, with `distance_km` set on it.
     */
    public static function nearestTo(float $latitude, float $longitude): ?self
    {
        return self::query()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get()
            ->each(fn (self $hospital) => $hospital->distance_km = round(
                self::distanceKm($latitude, $longitude, $hospital->latitude, $hospital->longitude),
                2,
            ))
            ->sortBy('distance_km')
            ->first();
    }
}
