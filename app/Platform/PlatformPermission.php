<?php

namespace App\Platform;

final class PlatformPermission
{
    public const DOMAINS = [
        'users',
        'customers',
        'drivers',
        'vehicles',
        'bookings',
        'payments',
        'wallets',
        'parcels',
        'travel',
        'fleet',
        'franchise',
        'corporate',
        'advertising',
        'safety',
        'reports',
        'fare',
    ];

    /**
     * Canonical granular abilities. Legacy `{domain}.read` / `{domain}.write` still work via aliases.
     *
     * @return list<string>
     */
    public static function catalog(): array
    {
        return [
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'customers.view', 'customers.edit',
            'drivers.view', 'drivers.create', 'drivers.edit', 'drivers.delete', 'drivers.approve',
            'vehicles.view', 'vehicles.edit',
            'bookings.view', 'bookings.manage',
            'payments.view', 'payments.edit',
            'wallet.view',
            'parcels.view', 'parcels.manage',
            'travel.view', 'travel.edit',
            'fleet.view', 'fleet.manage',
            'franchise.view', 'franchise.manage',
            'corporate.view', 'corporate.edit',
            'advertising.view', 'advertising.edit',
            'safety.view', 'safety.edit',
            'reports.view', 'reports.export',
            'fare.manage',
            'notifications.send',
            'state.operate',
            'platform.admin',
        ];
    }

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        $all = self::catalog();

        $territoryOps = [
            'users.view', 'users.create', 'users.edit',
            'customers.view',
            'drivers.view', 'drivers.create', 'drivers.edit', 'drivers.approve',
            'vehicles.view', 'vehicles.edit',
            'bookings.view', 'bookings.manage',
            'payments.view',
            'wallet.view',
            'parcels.view', 'parcels.manage',
            'travel.view',
            'fleet.view',
            'franchise.view', 'franchise.manage',
            'safety.view', 'safety.edit',
            'reports.view', 'reports.export',
            'notifications.send',
            'state.operate',
        ];

        return match ($role) {
            OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN => $all,
            OperatorRole::STATE_HEAD => $territoryOps,
            OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE => array_values(array_filter(
                $territoryOps,
                fn (string $ability) => ! in_array($ability, ['state.operate', 'notifications.send'], true),
            )),
            OperatorRole::FLEET_OWNER => [
                'drivers.view', 'drivers.create', 'drivers.edit',
                'vehicles.view', 'vehicles.edit',
                'bookings.view',
                'payments.view',
                'wallet.view',
                'fleet.view', 'fleet.manage',
                'reports.view', 'reports.export',
            ],
            OperatorRole::DRIVER => [
                'drivers.view',
                'vehicles.view',
                'bookings.view', 'bookings.manage',
                'wallet.view',
                'parcels.view', 'parcels.manage',
                'payments.view',
            ],
            OperatorRole::CUSTOMER => [
                'customers.view', 'customers.edit',
                'bookings.view', 'bookings.manage',
                'wallet.view',
                'parcels.view', 'parcels.manage',
                'travel.view',
                'payments.view',
            ],
            OperatorRole::CORPORATE => [
                'corporate.view', 'corporate.edit',
                'bookings.view', 'bookings.manage',
                'wallet.view',
                'payments.view',
                'travel.view',
            ],
            OperatorRole::ADVERTISER => [
                'advertising.view', 'advertising.edit',
            ],
            default => [],
        };
    }

    public static function allows(string $role, string $ability): bool
    {
        $granted = self::forRole($role);
        if (in_array($ability, $granted, true)) {
            return true;
        }

        foreach (self::expand($ability) as $canonical) {
            if (in_array($canonical, $granted, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Map legacy and shorthand codes onto canonical abilities.
     *
     * @return list<string>
     */
    public static function expand(string $ability): array
    {
        return match ($ability) {
            'users.read' => ['users.view'],
            'users.write' => ['users.create', 'users.edit'],
            'customers.read' => ['customers.view'],
            'customers.write' => ['customers.edit'],
            'drivers.read' => ['drivers.view'],
            'drivers.write' => ['drivers.create', 'drivers.edit', 'drivers.approve'],
            'vehicles.read' => ['vehicles.view'],
            'vehicles.write' => ['vehicles.edit'],
            'bookings.read' => ['bookings.view'],
            'bookings.write' => ['bookings.manage'],
            'payments.read' => ['payments.view'],
            'payments.write' => ['payments.edit'],
            'wallets.read', 'wallets.view' => ['wallet.view'],
            'wallets.write' => ['wallet.view'],
            'parcels.read' => ['parcels.view'],
            'parcels.write' => ['parcels.manage'],
            'travel.read' => ['travel.view'],
            'travel.write' => ['travel.edit'],
            'fleet.read' => ['fleet.view'],
            'fleet.write' => ['fleet.manage'],
            'franchise.read' => ['franchise.view'],
            'franchise.write' => ['franchise.manage'],
            'corporate.read' => ['corporate.view'],
            'corporate.write' => ['corporate.edit'],
            'advertising.read' => ['advertising.view'],
            'advertising.write' => ['advertising.edit'],
            'safety.read' => ['safety.view'],
            'safety.write' => ['safety.edit'],
            'fare.read', 'fare.write' => ['fare.manage'],
            default => [$ability],
        };
    }
}
