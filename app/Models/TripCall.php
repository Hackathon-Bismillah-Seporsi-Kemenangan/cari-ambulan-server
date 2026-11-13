<?php

namespace App\Models;

use App\Enums\CallStatus;
use App\Services\TripCallStateStore;
use Database\Factories\TripCallFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A voice call from the reporter to the driver of their active order.
 */
#[Fillable(['emergency_request_id', 'caller_id', 'room_name', 'status', 'rang_at', 'answered_at', 'ended_at'])]
class TripCall extends Model
{
    /** @use HasFactory<TripCallFactory> */
    use HasFactory, HasUuids;

    /**
     * How long a call rings before it counts as missed.
     */
    public const RING_SECONDS = 30;

    protected static function booted(): void
    {
        static::creating(function (self $call): void {
            $call->room_name ??= (string) Str::uuid();
        });

        // Cache is only an accelerator for live state, so a cache outage
        // must never break creating or updating a call in PostgreSQL/SQLite.
        static::saved(function (self $call): void {
            try {
                app(TripCallStateStore::class)->sync($call);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        static::deleted(function (self $call): void {
            try {
                app(TripCallStateStore::class)->forget($call);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'rang_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function emergencyRequest(): BelongsTo
    {
        return $this->belongsTo(EmergencyRequest::class, 'emergency_request_id');
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    /**
     * Calls still ringing or in progress.
     *
     * @param  Builder<self>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $query->where(fn (Builder $live) => $live
            ->where('status', CallStatus::Answered->value)
            ->orWhere(fn (Builder $ringing) => $ringing
                ->where('status', CallStatus::Ringing->value)
                ->where('rang_at', '>', self::ringCutoff())));
    }

    /**
     * Calls that started ringing at or before this moment are missed.
     */
    public static function ringCutoff(): Carbon
    {
        return now()->subSeconds(self::RING_SECONDS);
    }

    /**
     * Marks calls nobody answered in time as missed.
     */
    public static function markMissed(): void
    {
        self::query()
            ->where('status', CallStatus::Ringing->value)
            ->where('rang_at', '<=', self::ringCutoff())
            ->update(['status' => CallStatus::Missed->value, 'ended_at' => now()]);
    }

    public function hasStatus(CallStatus $status): bool
    {
        return $this->status === $status->value;
    }

    public function isParticipant(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;
        $emergency = $this->emergencyRequest;

        if ($emergency === null) {
            return false;
        }

        if ((string) $emergency->user_id === (string) $userId) {
            return true;
        }

        return $emergency->driverProfile !== null
            && (string) $emergency->driverProfile->user_id === (string) $userId;
    }

    public function markMissedIfExpired(): void
    {
        if ($this->hasStatus(CallStatus::Ringing) && ! $this->isRinging()) {
            $this->update([
                'status' => CallStatus::Missed->value,
                'ended_at' => now(),
            ]);
        }
    }

    public static function markMissedForTrip(EmergencyRequest $trip): void
    {
        $trip->calls()
            ->where('status', CallStatus::Ringing->value)
            ->where('rang_at', '<=', self::ringCutoff())
            ->get()
            ->each(fn (self $call) => $call->update([
                'status' => CallStatus::Missed->value,
                'ended_at' => now(),
            ]));
    }

    public function isRinging(): bool
    {
        return $this->hasStatus(CallStatus::Ringing) && $this->rang_at->isAfter(self::ringCutoff());
    }

    public function ringSecondsRemaining(): int
    {
        $endsAt = $this->rang_at->copy()->addSeconds(self::RING_SECONDS);

        return max(0, (int) ceil(now()->diffInMilliseconds($endsAt, false) / 1000));
    }
}

