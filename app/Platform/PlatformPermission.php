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
            'notifications.view',
            'notifications.send',
            'payout.view', 'payout.approve',
            'commission.edit',
            'agreement.view', 'agreement.upload',
            'complaints.manage',
            'district.delete', 'district.activate',
            'state.operate',
            'platform.admin',
            'dashboard.view',
            'state.view', 'state.create', 'state.update',
            'district.view', 'district.create', 'district.update',
            'state_head.view', 'state_head.create', 'state_head.update',
            'fleet_owner.view', 'fleet_owner.create', 'fleet_owner.update',
            'vehicle.create', 'vehicle.update',
            'driver.view', 'driver.create', 'driver.update',
            'tracking.view',
            'kyc.view', 'kyc.approve',
            'commission.view',
            'manager.view', 'manager.manage',
        ];
    }

    /**
     * @return list<string>
     */
    public static function forRole(string $role): array
    {
        $all = self::catalog();

        $stateHead = [
            'dashboard.view',
            'users.view', 'users.create',
            'customers.view',
            'drivers.view',
            'vehicles.view',
            'bookings.view',
            'parcels.view',
            'fleet.view',
            'franchise.view', 'franchise.manage',
            'reports.view', 'reports.export',
            'commission.view',
            'payout.view',
            'agreement.view', 'agreement.upload',
            'district.view', 'district.create', 'district.update', 'district.activate',
            'manager.view', 'manager.manage',
            'state_head.view',
            'notifications.view', 'notifications.send',
            'state.operate',
            'kyc.view',
            'tracking.view',
        ];

        $manager = [
            'dashboard.view',
            'district.view',
            'franchise.view', 'franchise.manage',
            'drivers.view', 'drivers.create', 'drivers.edit', 'drivers.approve',
            'vehicles.view', 'vehicles.edit', 'vehicle.create', 'vehicle.update',
            'bookings.view', 'bookings.manage',
            'parcels.view', 'parcels.manage',
            'customers.view',
            'complaints.manage',
            'safety.view', 'safety.edit',
            'kyc.view', 'kyc.approve',
            'fleet.view',
            'reports.view', 'reports.export',
            'agreement.view', 'agreement.upload',
        ];

        $franchise = [
            'dashboard.view',
            'district.view',
            'drivers.view', 'drivers.create', 'drivers.edit',
            'vehicles.view', 'vehicles.edit', 'vehicle.create', 'vehicle.update',
            'bookings.view', 'bookings.manage',
            'parcels.view', 'parcels.manage',
            'customers.view',
            'fleet.view',
            'commission.view',
            'payout.view',
            'reports.view', 'reports.export',
            'agreement.view',
            'franchise.view',
        ];

        return match ($role) {
            OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN => $all,
            OperatorRole::MANAGER => $manager,
            OperatorRole::STATE_HEAD => $stateHead,
            OperatorRole::DISTRICT_HEAD => array_values(array_unique(array_merge($manager, [
                'users.view',
                'district.view',
            ]))),
            OperatorRole::FRANCHISE => $franchise,
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
            'dashboard.view' => ['users.view', 'reports.view'],
            'state.view' => ['state.operate'],
            'state.create', 'state.update' => ['platform.admin'],
            'district.view' => ['users.view', 'state.operate'],
            'district.create', 'district.update' => ['platform.admin', 'state.operate'],
            'state_head.view' => ['users.view'],
            'state_head.create', 'state_head.update' => ['users.create', 'users.edit', 'platform.admin'],
            'fleet_owner.view' => ['fleet.view'],
            'fleet_owner.create', 'fleet_owner.update' => ['fleet.manage'],
            'driver.view' => ['drivers.view'],
            'driver.create' => ['drivers.create'],
            'driver.update' => ['drivers.edit'],
            'vehicle.view' => ['vehicles.view'],
            'vehicle.create', 'vehicle.update' => ['vehicles.edit'],
            'tracking.view' => ['vehicles.view'],
            'kyc.view' => ['drivers.view'],
            'kyc.approve' => ['drivers.approve'],
            'commission.view' => ['payments.view'],
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
