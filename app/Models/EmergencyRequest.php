<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Enums\EmergencyStatus;
use App\Models\Concerns\CalculatesDistance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Fillable([
    'request_code',
    'is_for_self',
    'emergency_type',
    'severity_level',
    'patient_notes',
    'pickup_latitude',
    'pickup_longitude',
    'pickup_address',
    'status',
    'search_radius_km',
    'radius_changed_at',
    'search_exhausted_at',
    'request_at',
    'accepted_at',
    'arrived_at_pickup_at',
    'picked_up_at',
    'completed_at',
    'cancelled_at',
    'cancel_reason',
    'hospital_id',
    'ambulance_id',
    'driver_profile_id',
    'user_id',
])]
class EmergencyRequest extends Model
{
    use CalculatesDistance, HasFactory, HasUuids;

    public const RADIUS_STEPS = [3, 5, 10, 15, 25];
    public const RADIUS_EXPAND_SECONDS = 90;

    protected function casts(): array
    {
        return [
            'is_for_self' => 'boolean',
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'search_radius_km' => 'integer',
            'radius_changed_at' => 'datetime',
            'search_exhausted_at' => 'datetime',
            'request_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arrived_at_pickup_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function ambulance(): BelongsTo
    {
        return $this->belongsTo(Ambulance::class, 'ambulance_id');
    }

    public function driverProfile(): BelongsTo
    {
        return $this->belongsTo(DriverProfile::class, 'driver_profile_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class, 'emergency_request_id');
    }

    public static function generateRequestCode(): string
    {
        return 'CA-' . Str::upper(Str::random(6));
    }

    public function hasStatus(EmergencyStatus $status): bool
    {
        return $this->status === $status->value;
    }

    public function isActiveTrip(): bool
    {
        return in_array(
            EmergencyStatus::tryFrom($this->status),
            EmergencyStatus::activeTrip(),
            strict: true,
        );
    }

    public function widenRadius(): bool
    {
        $current = (int) ($this->search_radius_km ?? 3);
        $next = null;

        foreach (self::RADIUS_STEPS as $step) {
            if ($step > $current) {
                $next = $step;
                break;
            }
        }

        if ($next !== null) {
            $this->update([
                'search_radius_km' => $next,
                'radius_changed_at' => now(),
            ]);
            return true;
        }

        return false;
    }

    /**
     * Offers this request to the nearest dispatchable driver within search radius.
     */
    public function offerToNextDriver(): ?DispatchLog
    {
        return DB::transaction(function (): ?DispatchLog {
            /** @var self $request */
            $request = self::query()->lockForUpdate()->findOrFail($this->id);

            $hasPendingOffer = $request->dispatchLogs()->pending()->exists();

            if (! $request->hasStatus(EmergencyStatus::SearchingDriver) || $hasPendingOffer) {
                return null;
            }

            $radius = (int) ($request->search_radius_km ?? 3);

            $candidates = DriverProfile::nearestDispatchableTo(
                $request->pickup_latitude,
                $request->pickup_longitude,
                $request->dispatchLogs()->pluck('driver_id')->all(),
            )->filter(fn (DriverProfile $candidate) => $candidate->distance_km <= $radius);

            $driver = $candidates->first(function (DriverProfile $candidate): bool {
                DriverProfile::query()->lockForUpdate()->find($candidate->id);
                return ! $candidate->dispatchLogs()->pending()->exists();
            });

            if ($driver === null) {
                if ($radius >= 25 && $request->search_exhausted_at === null) {
                    $request->update(['search_exhausted_at' => now()]);
                }
                return null;
            }

            if ($request->search_exhausted_at !== null) {
                $request->update(['search_exhausted_at' => null]);
            }

            return $request->dispatchLogs()->create([
                'driver_id' => $driver->id,
                'status' => DispatchStatus::Offered->value,
                'distance_km' => $driver->distance_km,
                'offered_at' => now(),
            ]);
        });
    }

    /**
     * Times out unanswered offers and redispatches waiting emergency requests.
     */
    public static function redispatchWaiting(): void
    {
        DispatchLog::query()
            ->where('status', DispatchStatus::Offered->value)
            ->where('offered_at', '<=', DispatchLog::expiryCutoff())
            ->update([
                'status' => DispatchStatus::Timeout->value,
                'responded_at' => now(),
            ]);

        self::query()
            ->where('status', EmergencyStatus::SearchingDriver->value)
            ->oldest('request_at')
            ->each(function (self $request): void {
                $baseTime = $request->radius_changed_at ?? $request->request_at;
                if ($baseTime !== null && ($request->search_radius_km ?? 3) < 25 && $baseTime->diffInSeconds(now()) >= self::RADIUS_EXPAND_SECONDS) {
                    $request->widenRadius();
                }

                if (! $request->dispatchLogs()->pending()->exists()) {
                    $request->offerToNextDriver();
                }
            });
    }
}

