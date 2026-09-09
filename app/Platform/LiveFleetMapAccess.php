<?php

namespace App\Platform;

use App\Models\User;

final class LiveFleetMapAccess
{
    public const ROLES = [
        OperatorRole::ADMIN,
        OperatorRole::SUPER_ADMIN,
        OperatorRole::STATE_HEAD,
        OperatorRole::DISTRICT_HEAD,
        OperatorRole::FLEET_OWNER,
    ];

    public const STATUSES = ['online', 'offline', 'on_trip', 'on_delivery', 'maintenance'];

    public const LABELS = [
        'online' => 'Online',
        'offline' => 'Offline',
        'on_trip' => 'On Trip',
        'on_delivery' => 'On Delivery',
        'maintenance' => 'Maintenance',
    ];

    public const LIVE_TRIPS = [
        'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'ONGOING', 'STARTED',
    ];

    public const LIVE_PARCELS = [
        'assigned', 'picked_up', 'in_transit', 'destination', 'out_for_delivery',
    ];

    public static function canUseMap(User $user): bool
    {
        return in_array($user->role, self::ROLES, true);
    }

    /**
     * @param  array{stored?: ?string, driverOnline?: bool, driverDuty?: ?string, hasActiveTrip?: bool, hasActiveDelivery?: bool}  $input
     * @return array{status: string, label: string}
     */
    public static function resolveStatus(array $input): array
    {
        $stored = strtolower((string) ($input['stored'] ?? ''));
        $duty = $input['driverDuty'] ?? null;
        if (in_array($stored, ['maintenance', 'under_maintenance'], true) || $duty === 'maintenance') {
            return ['status' => 'maintenance', 'label' => self::LABELS['maintenance']];
        }
        if ($stored === 'suspended' || $duty === 'suspended') {
            return ['status' => 'offline', 'label' => self::LABELS['offline']];
        }
        if (! empty($input['hasActiveDelivery']) || $duty === 'on_delivery') {
            return ['status' => 'on_delivery', 'label' => self::LABELS['on_delivery']];
        }
        if (! empty($input['hasActiveTrip']) || $duty === 'on_trip') {
            return ['status' => 'on_trip', 'label' => self::LABELS['on_trip']];
        }
        if ($duty === 'online' || ! empty($input['driverOnline'])) {
            return ['status' => 'online', 'label' => self::LABELS['online']];
        }

        return ['status' => 'offline', 'label' => self::LABELS['offline']];
    }

    /**
     * @return array<string, int>
     */
    public static function emptyCounts(): array
    {
        return array_fill_keys(self::STATUSES, 0);
    }
}
