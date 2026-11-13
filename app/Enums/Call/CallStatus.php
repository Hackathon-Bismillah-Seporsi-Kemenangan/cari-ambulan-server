<?php

namespace App\Enums\Call;

enum CallStatus: string
{
    case Initiated = 'initiated';
    case Ringing = 'ringing';
    case Accepted = 'accepted';
    case Missed = 'missed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
    case Failed = 'failed';

    /**
     * Check if the call can transition to accepted.
     */
    public function canBeAccepted(): bool
    {
        return $this === self::Ringing || $this === self::Initiated;
    }

    /**
     * Check if the call can transition to rejected.
     */
    public function canBeRejected(): bool
    {
        return $this === self::Ringing || $this === self::Initiated;
    }

    /**
     * Check if the call can be cancelled by caller.
     */
    public function canBeCancelled(): bool
    {
        return $this === self::Ringing || $this === self::Initiated;
    }

    /**
     * Check if the call can be ended by a participant.
     */
    public function canBeEnded(): bool
    {
        return in_array($this, [self::Initiated, self::Ringing, self::Accepted], true);
    }

    /**
     * Check if the call is in a final, terminal status.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Missed,
            self::Rejected,
            self::Cancelled,
            self::Ended,
            self::Failed,
        ], true);
    }

    /**
     * Check if participants can join or reconnect to the room.
     */
    public function isJoinable(): bool
    {
        return in_array($this, [self::Initiated, self::Ringing, self::Accepted], true);
    }
}
