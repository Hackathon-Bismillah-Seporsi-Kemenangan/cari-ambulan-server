<?php

namespace App\Models;

use App\Enums\Call\CallEndReason;
use App\Enums\Call\CallStatus;
use Database\Factories\CallFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Call extends Model
{
    /** @use HasFactory<CallFactory> */
    use HasFactory, HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'caller_id',
        'receiver_id',
        'callee_id', // for backward compatibility
        'room_name',
        'call_type',
        'status',
        'end_reason',
        'started_at',
        'answered_at',
        'accepted_at', // for backward compatibility
        'ended_at',
        'ended_by_id',
        'duration_seconds',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'end_reason' => CallEndReason::class,
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'accepted_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Call $call): void {
            if ($call->callee_id && ! $call->receiver_id) {
                $call->receiver_id = $call->callee_id;
            } elseif ($call->receiver_id && ! $call->callee_id) {
                $call->callee_id = $call->receiver_id;
            }
        });
    }

    public function setCalleeIdAttribute($value): void
    {
        $this->attributes['callee_id'] = $value;
        $this->attributes['receiver_id'] = $value;
    }

    public function setReceiverIdAttribute($value): void
    {
        $this->attributes['receiver_id'] = $value;
        $this->attributes['callee_id'] = $value;
    }

    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caller_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, $this->receiver_id ? 'receiver_id' : 'callee_id');
    }

    /**
     * Backward-compatible alias for receiver.
     */
    public function callee(): BelongsTo
    {
        return $this->receiver();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(CallParticipant::class);
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by_id');
    }

    public function isCaller(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (string) $this->caller_id === (string) $userId;
    }

    public function isReceiver(User|string $user): bool
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (string) $this->receiver_id === (string) $userId
            || ($this->callee_id !== null && (string) $this->callee_id === (string) $userId);
    }

    /**
     * Backward-compatible alias for isReceiver.
     */
    public function isCallee(User|string $user): bool
    {
        return $this->isReceiver($user);
    }

    public function isParticipant(User|string $user): bool
    {
        return $this->isCaller($user) || $this->isReceiver($user);
    }

    public function canBeAccepted(): bool
    {
        return $this->status instanceof CallStatus
            ? $this->status->canBeAccepted()
            : in_array((string) $this->status, ['initiated', 'ringing'], true);
    }

    public function canBeRejected(): bool
    {
        return $this->status instanceof CallStatus
            ? $this->status->canBeRejected()
            : in_array((string) $this->status, ['initiated', 'ringing'], true);
    }

    public function canBeCancelled(): bool
    {
        return $this->status instanceof CallStatus
            ? $this->status->canBeCancelled()
            : in_array((string) $this->status, ['initiated', 'ringing'], true);
    }

    public function canBeEnded(): bool
    {
        return $this->status instanceof CallStatus
            ? $this->status->canBeEnded()
            : in_array((string) $this->status, ['initiated', 'ringing', 'accepted', 'active'], true);
    }

    public function isJoinable(): bool
    {
        return $this->status instanceof CallStatus
            ? $this->status->isJoinable()
            : in_array((string) $this->status, ['initiated', 'ringing', 'accepted', 'active'], true);
    }

    /**
     * Determine the actual media connection start time (when both participants joined).
     * If participant joined timestamps are not recorded, falls back to answered_at or started_at.
     */
    public function getMediaStartTime(): ?Carbon
    {
        $participants = $this->relationLoaded('participants')
            ? $this->participants
            : $this->participants()->get();

        if ($participants->count() >= 2) {
            $joinedTimes = $participants->pluck('joined_at')->filter();
            if ($joinedTimes->count() >= 2) {
                return $joinedTimes->max();
            }
        }

        return $this->answered_at ?? $this->accepted_at ?? $this->started_at;
    }

    /**
     * Scope query to ongoing calls.
     *
     * @param  Builder<Call>  $query
     */
    public function scopeOngoing(Builder $query): void
    {
        $query->whereIn('status', [
            CallStatus::Initiated->value,
            CallStatus::Ringing->value,
            CallStatus::Accepted->value,
            'active', // backward compat
        ]);
    }

    /**
     * Scope query to calls where the user is either caller or receiver.
     *
     * @param  Builder<Call>  $query
     */
    public function scopeForUser(Builder $query, User|string $user): void
    {
        $userId = $user instanceof User ? $user->id : $user;

        $query->where(function (Builder $q) use ($userId): void {
            $q->where('caller_id', $userId)
                ->orWhere('receiver_id', $userId)
                ->orWhere('callee_id', $userId);
        });
    }
}
