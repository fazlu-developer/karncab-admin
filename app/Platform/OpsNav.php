<?php

namespace App\Platform;

final class OpsNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, ability: ?string, route?: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'layout-dashboard', 'group' => 'Overview', 'ability' => null, 'route' => 'dashboard'],
            ['key' => 'state-workspace', 'label' => 'State workspace', 'icon' => 'landmark', 'group' => 'Overview', 'ability' => 'state.operate', 'route' => 'state.dashboard'],
            ['key' => 'state-assignments', 'label' => 'State assignment', 'icon' => 'map', 'group' => 'Overview', 'ability' => 'platform.admin', 'route' => 'state.assignments'],
            ['key' => 'users', 'label' => 'Users', 'icon' => 'users', 'group' => 'People', 'ability' => 'users.view', 'route' => 'users.index'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'People', 'ability' => 'drivers.view', 'route' => 'drivers.index'],
            ['key' => 'fleet', 'label' => 'Fleet Owners', 'icon' => 'warehouse', 'group' => 'People', 'ability' => 'fleet.view'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'People', 'ability' => 'vehicles.view'],
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Rides', 'ability' => 'bookings.view'],
            ['key' => 'parcels', 'label' => 'Parcel', 'icon' => 'package', 'group' => 'Rides', 'ability' => 'parcels.view'],
            ['key' => 'travel', 'label' => 'Travel Packages', 'icon' => 'map', 'group' => 'Rides', 'ability' => 'travel.view'],
            ['key' => 'travel-bookings', 'label' => 'Travel Bookings', 'icon' => 'tickets', 'group' => 'Rides', 'ability' => 'travel.view'],
            ['key' => 'bulk', 'label' => 'Bulk Booking', 'icon' => 'layers', 'group' => 'Rides', 'ability' => 'bookings.view'],
            ['key' => 'corporate', 'label' => 'Corporate', 'icon' => 'building-2', 'group' => 'Partners', 'ability' => 'corporate.view'],
            ['key' => 'state-heads', 'label' => 'State Heads', 'icon' => 'landmark', 'group' => 'Partners', 'ability' => 'users.view'],
            ['key' => 'district-heads', 'label' => 'District Heads', 'icon' => 'map-pinned', 'group' => 'Partners', 'ability' => 'users.view'],
            ['key' => 'franchise', 'label' => 'Franchise', 'icon' => 'store', 'group' => 'Partners', 'ability' => 'franchise.view', 'route' => 'franchises.index'],
            ['key' => 'kyc', 'label' => 'KYC', 'icon' => 'badge-check', 'group' => 'Trust', 'ability' => 'drivers.view'],
            ['key' => 'payments', 'label' => 'Payments', 'icon' => 'credit-card', 'group' => 'Finance', 'ability' => 'payments.view', 'route' => 'payments.index'],
            ['key' => 'wallets', 'label' => 'Wallet', 'icon' => 'wallet', 'group' => 'Finance', 'ability' => 'wallet.view', 'route' => 'wallets.index'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'ability' => 'payments.view', 'route' => 'wallets.commission'],
            ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket-percent', 'group' => 'Finance', 'ability' => 'platform.admin', 'route' => 'coupons.index'],
            ['key' => 'advertising', 'label' => 'Advertising', 'icon' => 'megaphone', 'group' => 'Growth', 'ability' => 'advertising.view', 'route' => 'ads.index'],
            ['key' => 'map', 'label' => 'Live Map', 'icon' => 'radar', 'group' => 'Operations', 'ability' => 'vehicles.view', 'route' => 'live.map'],
            ['key' => 'safety', 'label' => 'Safety & SOS', 'icon' => 'shield-alert', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'safety.index'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Operations', 'ability' => 'safety.view', 'route' => 'support.index'],
            ['key' => 'ratings', 'label' => 'Ratings', 'icon' => 'star', 'group' => 'Operations', 'ability' => 'bookings.view'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Operations', 'ability' => 'reports.view', 'route' => 'reports.index'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'notifications.index'],
            ['key' => 'fare', 'label' => 'Fare Management', 'icon' => 'banknote', 'group' => 'System', 'ability' => 'fare.manage', 'route' => 'fare.index'],
            ['key' => 'ride-settings', 'label' => 'Ride Settings', 'icon' => 'radar', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ride-settings.index'],
            ['key' => 'services', 'label' => 'Service Management', 'icon' => 'settings-2', 'group' => 'System', 'ability' => 'platform.admin'],
            ['key' => 'locations', 'label' => 'Locations', 'icon' => 'globe', 'group' => 'System', 'ability' => 'platform.admin'],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => 'key-round', 'group' => 'System', 'ability' => 'platform.admin'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'sliders-horizontal', 'group' => 'System', 'ability' => 'platform.admin'],
            ['key' => 'audit', 'label' => 'Audit Logs', 'icon' => 'scroll-text', 'group' => 'System', 'ability' => 'platform.admin'],
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

    public static function href(array $item): string
    {
        if (! empty($item['route'])) {
            return route($item['route']);
        }

        return route('ops.module', $item['key']);
    }

    public static function active(array $item): bool
    {
        if (($item['route'] ?? '') === 'dashboard') {
            return request()->routeIs('dashboard');
        }
        if (($item['route'] ?? '') === 'state.dashboard') {
            return request()->routeIs('state.*') && ! request()->routeIs('state.assignments*');
        }
        if (($item['route'] ?? '') === 'state.assignments') {
            return request()->routeIs('state.assignments*');
        }
        if (($item['key'] ?? '') === 'users') {
            return request()->routeIs('users.*');
        }
        if (($item['key'] ?? '') === 'drivers') {
            return request()->routeIs('drivers.*');
        }

        if (($item['key'] ?? '') === 'franchise') {
            return request()->routeIs('franchises.*');
        }

        if (($item['key'] ?? '') === 'map') {
            return request()->routeIs('live.map');
        }
        if (($item['key'] ?? '') === 'wallets') {
            return request()->routeIs('wallets.*') && ! request()->routeIs('wallets.commission*');
        }
        if (($item['key'] ?? '') === 'commission') {
            return request()->routeIs('wallets.commission*');
        }
        if (($item['key'] ?? '') === 'payments') {
            return request()->routeIs('payments.*');
        }
        if (($item['key'] ?? '') === 'advertising') {
            return request()->routeIs('ads.*');
        }
        if (($item['key'] ?? '') === 'coupons') {
            return request()->routeIs('coupons.*');
        }
        if (($item['key'] ?? '') === 'safety') {
            return request()->routeIs('safety.*') && ! request()->routeIs('safety.share');
        }
        if (($item['key'] ?? '') === 'complaints') {
            return request()->routeIs('support.*');
        }
        if (($item['key'] ?? '') === 'notifications') {
            return request()->routeIs('notifications.*');
        }
        if (($item['key'] ?? '') === 'fare') {
            return request()->routeIs('fare.*');
        }
        if (($item['key'] ?? '') === 'ride-settings') {
            return request()->routeIs('ride-settings.*');
        }
        if (($item['key'] ?? '') === 'reports') {
            return request()->routeIs('reports.*');
        }

        return request()->route('module') === ($item['key'] ?? null);
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

    /**
     * @return list<array<string, mixed>>
     */
    public static function visibleFor(object $user): array
    {
        $out = [];
        foreach (self::items() as $item) {
            if ($item['ability'] === null || (method_exists($user, 'can') && $user->can($item['ability']))) {
                $out[] = $item;
            }
        }

        return $out;
    }
}
