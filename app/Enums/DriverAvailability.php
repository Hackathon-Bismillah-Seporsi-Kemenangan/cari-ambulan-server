<?php

namespace App\Enums;

/**
 * driver_profiles.availability_status
 */
enum DriverAvailability: string
{
    case Offline = 'offline';
    case Online = 'online';
    case Busy = 'busy';
}

