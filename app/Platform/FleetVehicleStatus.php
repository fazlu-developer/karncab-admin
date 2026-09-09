<?php

namespace App\Platform;

final class FleetVehicleStatus
{
    public const STATUSES = [
        'available',
        'online',
        'on_trip',
        'offline',
        'maintenance',
        'suspended',
    ];

    public const WRITABLE = ['available', 'offline', 'maintenance', 'suspended'];

    public const LOCKED = ['maintenance', 'suspended'];

    public const LABELS = [
        'available' => 'Available',
        'online' => 'Online',
        'on_trip' => 'On Trip',
        'offline' => 'Offline',
        'maintenance' => 'Maintenance',
        'suspended' => 'Suspended',
    ];

    public const CATEGORIES = ['BIKE', 'AUTO', 'E_RICKSHAW', 'MINI', 'SEDAN', 'SUV', 'TRAVELLER'];

    public const DOC_TYPES = ['RC', 'INSURANCE', 'PERMIT', 'FITNESS', 'PUC', 'PHOTO'];

    public const DOC_LABELS = [
        'RC' => 'Registration certificate',
        'INSURANCE' => 'Insurance',
        'PERMIT' => 'Permit',
        'FITNESS' => 'Fitness certificate',
        'PUC' => 'Pollution certificate',
        'PHOTO' => 'Vehicle photo',
    ];

    public const DRIVER_DOC_TYPES = ['LICENSE', 'AADHAAR', 'PAN', 'PHOTO', 'BADGE'];

    public const ACTIVE_TRIPS = [
        'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'STARTED', 'ONGOING',
    ];

    public static function normalize(?string $value): string
    {
        return in_array($value, self::STATUSES, true) ? $value : 'offline';
    }

    /**
     * @param  array{stored: string, assignedDriverId?: mixed, driverOnline?: bool, driverDuty?: ?string, hasActiveTrip?: bool}  $input
     * @return array{status: string, label: string, locked: bool}
     */
    public static function resolve(array $input): array
    {
        $stored = self::normalize($input['stored'] ?? null);
        if (in_array($stored, self::LOCKED, true)) {
            return ['status' => $stored, 'label' => self::LABELS[$stored], 'locked' => true];
        }
        $status = 'offline';
        if (! empty($input['hasActiveTrip'])) {
            $status = 'on_trip';
        } elseif (($input['driverDuty'] ?? null) === 'online' || ! empty($input['driverOnline'])) {
            $status = 'online';
        } elseif (! empty($input['assignedDriverId'])) {
            $status = 'available';
        }

        return ['status' => $status, 'label' => self::LABELS[$status], 'locked' => false];
    }

    public static function isLocked(?string $status): bool
    {
        return in_array(self::normalize($status), self::LOCKED, true);
    }
}
