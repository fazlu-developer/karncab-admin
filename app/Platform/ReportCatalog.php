<?php

namespace App\Platform;

final class ReportCatalog
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        return [
            ['key' => 'daily_bookings', 'label' => 'Daily bookings', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'monthly_bookings', 'label' => 'Monthly bookings', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'completed_rides', 'label' => 'Completed rides', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'cancelled_rides', 'label' => 'Cancelled rides', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'driver_performance', 'label' => 'Driver performance', 'group' => 'Operations', 'ability' => 'drivers.view'],
            ['key' => 'fleet_utilization', 'label' => 'Fleet utilization', 'group' => 'Operations', 'ability' => 'vehicles.view'],
            ['key' => 'district_performance', 'label' => 'District performance', 'group' => 'Operations', 'ability' => 'bookings.view', 'roles' => [
                OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN, OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE,
            ]],
            ['key' => 'state_performance', 'label' => 'State performance', 'group' => 'Operations', 'ability' => 'bookings.view', 'roles' => [
                OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN, OperatorRole::STATE_HEAD,
            ]],
            ['key' => 'gross_revenue', 'label' => 'Gross revenue', 'group' => 'Finance', 'ability' => 'payments.view'],
            ['key' => 'net_revenue', 'label' => 'Net revenue', 'group' => 'Finance', 'ability' => 'payments.view'],
            ['key' => 'driver_earnings', 'label' => 'Driver earnings', 'group' => 'Finance', 'ability' => 'wallet.view'],
            ['key' => 'fleet_earnings', 'label' => 'Fleet earnings', 'group' => 'Finance', 'ability' => 'wallet.view'],
            ['key' => 'franchise_commission', 'label' => 'Franchise commission', 'group' => 'Finance', 'ability' => 'franchise.view', 'roles' => [
                OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN, OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE,
            ]],
            ['key' => 'platform_commission', 'label' => 'Platform commission', 'group' => 'Finance', 'ability' => 'payments.view', 'roles' => [
                OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN,
            ]],
            ['key' => 'payment_methods', 'label' => 'Payment method reports', 'group' => 'Finance', 'ability' => 'payments.view'],
            ['key' => 'refunds', 'label' => 'Refund reports', 'group' => 'Finance', 'ability' => 'payments.view'],
            ['key' => 'wallets', 'label' => 'Wallet reports', 'group' => 'Finance', 'ability' => 'wallet.view'],
            ['key' => 'new_users', 'label' => 'New users', 'group' => 'Customer', 'ability' => 'customers.view'],
            ['key' => 'active_users', 'label' => 'Active users', 'group' => 'Customer', 'ability' => 'customers.view'],
            ['key' => 'repeat_customers', 'label' => 'Repeat customers', 'group' => 'Customer', 'ability' => 'customers.view'],
            ['key' => 'ratings', 'label' => 'Ratings', 'group' => 'Customer', 'ability' => 'bookings.view'],
            ['key' => 'complaints', 'label' => 'Complaints', 'group' => 'Customer', 'ability' => 'safety.view'],
            ['key' => 'parcel_orders', 'label' => 'Parcel orders', 'group' => 'Parcel', 'ability' => 'parcels.view'],
            ['key' => 'parcel_success', 'label' => 'Delivery success', 'group' => 'Parcel', 'ability' => 'parcels.view'],
            ['key' => 'parcel_failed', 'label' => 'Failed deliveries', 'group' => 'Parcel', 'ability' => 'parcels.view'],
            ['key' => 'parcel_revenue', 'label' => 'Parcel revenue', 'group' => 'Parcel', 'ability' => 'parcels.view'],
            ['key' => 'parcel_driver_performance', 'label' => 'Parcel driver performance', 'group' => 'Parcel', 'ability' => 'parcels.view'],
        ];
    }

    public static function find(string $key): ?array
    {
        foreach (self::items() as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function visibleFor(object $user): array
    {
        $out = [];
        foreach (self::items() as $item) {
            if (! self::allows($user, $item)) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    public static function allows(object $user, array $item): bool
    {
        if (! method_exists($user, 'can') || ! $user->can('reports.view') || ! $user->can($item['ability'])) {
            return false;
        }
        $roles = $item['roles'] ?? null;
        if (is_array($roles) && $roles !== []) {
            return in_array((string) $user->role, $roles, true);
        }

        return true;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(object $user): array
    {
        $groups = [];
        foreach (self::visibleFor($user) as $item) {
            $groups[$item['group']][] = $item;
        }

        return $groups;
    }
}
