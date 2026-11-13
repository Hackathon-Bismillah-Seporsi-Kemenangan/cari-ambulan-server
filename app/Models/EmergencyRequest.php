<?php

namespace App\Models;

use App\Enums\DispatchStatus;
use App\Enums\DriverAvailability;
use App\Enums\EmergencyStatus;
use App\Models\Concerns\CalculatesDistance;
use Database\Factories\EmergencyRequestFactory;
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
    use CalculatesDistance;

    /** @use HasFactory<EmergencyRequestFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * Search radius step progression in kilometers.
     *
     * @var list<int>
     */
    public const RADIUS_STEPS = [3, 5, 10, 15, 25];

    /**
     * How long before search radius widens to the next tier.
     */
    public const RADIUS_EXPAND_SECONDS = 20;

    /**
     * How many nearby drivers ring at the same time. The first to accept
     * wins; the others' offers are closed.
     */
    public const OFFER_FANOUT = 3;

    /**
     * An open order with no update for this long is treated as abandoned
     * (e.g. the driver never finished the trip) and closed, so the reporter's
     * app isn't stuck on it.
     */
    public const ABANDONED_AFTER_HOURS = 2;

    /**
     * What the IGD prepares for each emergency type, told to the driver.
     *
     * @var array<string, string>
     */
    private const IGD_PREPARATION = [
        'Kecelakaan' => 'IGD menyiapkan tim trauma.',
        'Henti Jantung' => 'IGD menyiapkan tim jantung.',
        'Kehamilan' => 'IGD menyiapkan tim kebidanan.',
        'Sesak Nafas' => 'IGD menyiapkan tim pernapasan.',
    ];

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

    public function tripLocationLogs(): HasMany
    {
        return $this->hasMany(TripLocationLog::class, 'emergency_request_id');
    }

    public function dispatchLogs(): HasMany
    {
        return $this->hasMany(DispatchLog::class, 'emergency_request_id');
    }

    /**
     * Still searching for a driver or being served.
     *
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', self::openStatuses());
    }

    public static function generateRequestCode(): string
    {
        return 'CA-'.Str::upper(Str::random(6));
    }

    public function hasStatus(EmergencyStatus $status): bool
    {
        return $this->status === $status->value;
    }

    /**
     * A driver is on the way or the patient is on board.
     */
    public function isActiveTrip(): bool
    {
        return in_array(
            EmergencyStatus::tryFrom($this->status),
            EmergencyStatus::activeTrip(),
            strict: true,
        );
    }

    public function igdPreparation(): string
    {
        return self::IGD_PREPARATION[$this->emergency_type] ?? 'IGD sudah menerima data pasien.';
    }

    public function radiusExpandsInSeconds(): int
    {
        if (! $this->hasStatus(EmergencyStatus::SearchingDriver) || $this->search_exhausted_at !== null || ($this->search_radius_km ?? 3) >= 25) {
            return 0;
        }

        $baseTime = $this->radius_changed_at ?? $this->request_at ?? now();
        $elapsed = (int) $baseTime->diffInSeconds(now());

        return max(0, self::RADIUS_EXPAND_SECONDS - $elapsed);
    }

    public function isSearchExhausted(): bool
    {
        return $this->search_exhausted_at !== null;
    }

    /**
     * Advance the search radius to the next tier (3 -> 5 -> 10 -> 15 -> 25 km).
     */
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

    public function acceptByDriver(DriverProfile $driver, DispatchLog $offer): bool
    {
        return DB::transaction(function () use ($driver, $offer): bool {
            /** @var self $locked */
            $locked = self::query()->lockForUpdate()->find($this->id);

            if (! $locked->hasStatus(EmergencyStatus::SearchingDriver) || $offer->status !== DispatchStatus::Offered->value) {
                return false;
            }

            $ambulance = $driver->ambulance;

            $locked->update([
                'status' => EmergencyStatus::DriverAssigned->value,
                'accepted_at' => now(),
                'driver_profile_id' => $driver->id,
                'ambulance_id' => $ambulance?->id,
                'hospital_id' => $driver->hospital_id,
            ]);

            $offer->update([
                'status' => DispatchStatus::Accepted->value,
                'responded_at' => now(),
            ]);

            $driver->update([
                'availability_status' => DriverAvailability::Busy->value,
            ]);

            // Other drivers ringing for this request stop ringing.
            $locked->dispatchLogs()->pending()->where('id', '!=', $offer->id)->update([
                'status' => DispatchStatus::Timeout->value,
                'responded_at' => now(),
            ]);

            return true;
        });
    }

    public function declineByDriver(DriverProfile $driver, DispatchLog $offer): ?DispatchLog
    {
        return DB::transaction(function () use ($offer): ?DispatchLog {
            $offer->update([
                'status' => DispatchStatus::Declined->value,
                'responded_at' => now(),
            ]);

            return $this->offerToNextDriver();
        });
    }

    /**
     * Progress active trip to the next logical stage.
     */
    public function progressTripStatus(EmergencyStatus $targetStatus): bool
    {
        $currentStatus = EmergencyStatus::tryFrom($this->status);

        $allowedNext = match ($currentStatus) {
            EmergencyStatus::DriverAssigned => [EmergencyStatus::EnRouteToPickup],
            EmergencyStatus::EnRouteToPickup => [EmergencyStatus::ArriveAtPickup],
            EmergencyStatus::ArriveAtPickup => [EmergencyStatus::EnRouteToHospital],
            EmergencyStatus::EnRouteToHospital => [EmergencyStatus::Completed],
            default => [],
        };

        if (! in_array($targetStatus, $allowedNext, strict: true)) {
            return false;
        }

        return DB::transaction(function () use ($targetStatus): bool {
            $updates = [
                'status' => $targetStatus->value,
            ];

            match ($targetStatus) {
                EmergencyStatus::ArriveAtPickup => $updates['arrived_at_pickup_at'] = now(),
                EmergencyStatus::EnRouteToHospital => $updates['picked_up_at'] = now(),
                EmergencyStatus::Completed => $updates['completed_at'] = now(),
                default => null,
            };

            $this->update($updates);

            if ($targetStatus === EmergencyStatus::Completed && $this->driverProfile !== null) {
                $this->driverProfile->update([
                    'availability_status' => DriverAvailability::Online->value,
                ]);
            }

            return true;
        });
    }

    /**
     * Offers this request to the nearest dispatchable driver who hasn't been
     * offered it yet, up to OFFER_FANOUT simultaneous offers.
     */
    public function offerToNextDriver(): ?DispatchLog
    {
        return DB::transaction(function (): ?DispatchLog {
            /** @var self $request */
            $request = self::query()->lockForUpdate()->findOrFail($this->id);

            if (! $request->hasStatus(EmergencyStatus::SearchingDriver)) {
                return null;
            }

            $slots = self::OFFER_FANOUT - $request->dispatchLogs()->pending()->count();

            if ($slots <= 0) {
                return null;
            }

            $radius = (int) ($request->search_radius_km ?? 3);

            $candidates = DriverProfile::nearestDispatchableTo(
                $request->pickup_latitude,
                $request->pickup_longitude,
                $request->dispatchLogs()->pluck('driver_id')->all(),
                $radius,
            )->filter(fn (DriverProfile $candidate) => $candidate->distance_km <= $radius);

            $first = null;

            foreach ($candidates as $candidate) {
                if ($slots <= 0) {
                    break;
                }

                // Another request may be dispatching at the same moment: lock
                // the driver and re-check so nobody gets two calls at once.
                DriverProfile::query()->lockForUpdate()->find($candidate->id);

                if ($candidate->dispatchLogs()->pending()->exists()) {
                    continue;
                }

                $log = $request->dispatchLogs()->create([
                    'driver_id' => $candidate->id,
                    'status' => DispatchStatus::Offered->value,
                    'distance_km' => $candidate->distance_km,
                    'offered_at' => now(),
                ]);

                $first ??= $log;
                $slots--;
            }

            if ($first === null) {
                if ($radius >= 25 && $request->search_exhausted_at === null && ! $request->dispatchLogs()->pending()->exists()) {
                    $request->update(['search_exhausted_at' => now()]);
                }

                return null;
            }

            if ($request->search_exhausted_at !== null) {
                $request->update(['search_exhausted_at' => null]);
            }

            return $first;
        });
    }

    /**
     * Cancels open orders with no status change and no driver GPS for
     * ABANDONED_AFTER_HOURS. Pending offers time out and the driver is released offline.
     */
    public static function closeAbandoned(): int
    {
        $cutoff = now()->subHours(self::ABANDONED_AFTER_HOURS);

        $abandoned = self::query()
            ->open()
            ->where('updated_at', '<=', $cutoff)
            ->whereDoesntHave('tripLocationLogs', fn (Builder $log) => $log->where('created_at', '>', $cutoff))
            ->pluck('id');

        foreach ($abandoned as $id) {
            DB::transaction(function () use ($id): void {
                $emergency = self::query()->lockForUpdate()->find($id);
                if ($emergency === null || ! in_array($emergency->status, self::openStatuses(), strict: true)) {
                    return;
                }

                $emergency->update([
                    'status' => EmergencyStatus::Cancelled->value,
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Ditutup otomatis: tidak ada pembaruan selama '.self::ABANDONED_AFTER_HOURS.' jam.',
                ]);

                $emergency->dispatchLogs()->pending()->update([
                    'status' => DispatchStatus::Timeout->value,
                    'responded_at' => now(),
                ]);

                $driver = $emergency->driverProfile()->lockForUpdate()->first();
                if ($driver !== null && $driver->activeTrip() === null) {
                    $driver->update(['availability_status' => DriverAvailability::Offline->value]);
                }
            });
        }

        return $abandoned->count();
    }

    /**
     * @return list<string>
     */
    public static function openStatuses(): array
    {
        return array_map(
            fn (EmergencyStatus $status) => $status->value,
            [EmergencyStatus::SearchingDriver, ...EmergencyStatus::activeTrip()],
        );
    }

    /**
     * Times out unanswered offers and offers every waiting request to the
     * next available driver. Safe to call often.
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

                $request->offerToNextDriver();
            });
    }
}
