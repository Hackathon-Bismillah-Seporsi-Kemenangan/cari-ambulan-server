<?php

namespace App\Enums;

/**
 * Outcome of offering a request to one driver (dispatch_logs.status).
 */
enum DispatchStatus: string
{
    case Offered = 'offered';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Timeout = 'timeout';
    case Cancelled = 'cancelled';
}

