<?php

namespace App\Enums;

/**
 * Lifecycle of an emergency request (emergency_requests.status).
 */
enum EmergencyStatus: string
{
    case SearchingDriver = 'searching_driver';
    case DriverAssigned = 'driver_assigned';
    case EnRouteToPickup = 'en_route_to_pickup';
    case ArriveAtPickup = 'arrive_at_pickup';
    case EnRouteToHospital = 'en_route_to_hospital';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Statuses in which a driver is actively serving the request.
     *
     * @return list<self>
     */
    public static function activeTrip(): array
    {
        return [
            self::DriverAssigned,
            self::EnRouteToPickup,
            self::ArriveAtPickup,
            self::EnRouteToHospital,
        ];
    }

    /**
     * All ongoing request statuses (searching or active trip).
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [
            self::SearchingDriver,
            ...self::activeTrip(),
        ];
    }

    /**
     * @return list<string>
     */
    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::active());
    }

    /**
     * @return list<string>
     */
    public static function activeTripValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::activeTrip());
    }
}

