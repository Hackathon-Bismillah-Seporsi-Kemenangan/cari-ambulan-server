<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['emergency_request_id', 'driver_id', 'status', 'distance_km', 'offered_at', 'responded_at'])]
class DispatchLog extends Model
{
    use HasFactory;

    /**
     * How long a driver has to accept an offer before it moves on (30 seconds).
     */
    public const OFFER_SECONDS = 30;

    protected function casts(): array
    {
        return [
            'distance_km' => 'float',
            'offered_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function emergencyRequest(): BelongsTo
    {
        return $this->belongsTo(EmergencyRequest::class, 'emergency_request_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_id');
    }

    public static function expiryCutoff(): Carbon
    {
        return now()->subSeconds(self::OFFER_SECONDS);
    }

    /**
     * Offers still pending response from the driver.
     *
     * @param  Builder<self>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', DispatchStatus::Offered->value)
            ->where('offered_at', '>', self::expiryCutoff());
    }

    public function isPending(): bool
    {
        return $this->status === DispatchStatus::Offered->value && $this->secondsRemaining() > 0;
    }

    public function expiresAt(): Carbon
    {
        return $this->offered_at->copy()->addSeconds(self::OFFER_SECONDS);
    }

    public function secondsRemaining(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expiresAt(), false));
    }

    /**
     * Rough drive time at an average 25 km/h in city traffic.
     */
    public static function drivingMinutes(float $distanceKm): int
    {
        return max(1, (int) ceil($distanceKm / 25 * 60));
    }
}
