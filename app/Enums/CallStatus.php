<?php

namespace App\Enums;

/**
 * trip_calls.status
 */
enum CallStatus: string
{
    case Ringing = 'ringing';
    case Active = 'active';
    case Ended = 'ended';
    case Rejected = 'rejected';
    case Missed = 'missed';
    case Failed = 'failed';

    // Legacy statuses for trip_calls backward compatibility
    case Answered = 'answered';
    case Declined = 'declined';
}

