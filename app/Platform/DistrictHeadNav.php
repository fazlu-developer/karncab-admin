<?php

namespace App\Platform;

final class DistrictHeadNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, ability: string, route?: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'District dashboard', 'icon' => 'layout-dashboard', 'group' => 'Overview', 'ability' => 'dashboard.view', 'route' => 'district.dashboard'],
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Operations', 'ability' => 'bookings.view', 'route' => 'ops.module', 'module' => 'bookings'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Network', 'ability' => 'drivers.view', 'route' => 'drivers.index'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Network', 'ability' => 'vehicles.view', 'route' => 'vehicles.index'],
            ['key' => 'fleets', 'label' => 'Fleets', 'icon' => 'warehouse', 'group' => 'Network', 'ability' => 'fleet.view', 'route' => 'fleet-owners.index'],
            ['key' => 'franchises', 'label' => 'Franchises', 'icon' => 'store', 'group' => 'Network', 'ability' => 'franchise.view', 'route' => 'franchises.index'],
            ['key' => 'map', 'label' => 'Live tracking', 'icon' => 'radar', 'group' => 'Operations', 'ability' => 'tracking.view', 'route' => 'live.map'],
            ['key' => 'wallets', 'label' => 'Wallets', 'icon' => 'wallet', 'group' => 'Finance', 'ability' => 'wallet.view', 'route' => 'wallets.index'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'ability' => 'commission.view', 'route' => 'wallets.commission'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Finance', 'ability' => 'reports.view', 'route' => 'reports.index'],
            ['key' => 'fleet-cut', 'label' => 'Fleet commission', 'icon' => 'sliders-horizontal', 'group' => 'Finance', 'ability' => 'fleet.manage', 'route' => 'district.settings'],
            ['key' => 'fare', 'label' => 'Fare Management', 'icon' => 'banknote', 'group' => 'System', 'ability' => 'fare.manage', 'route' => 'fare.index'],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => 'key-round', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.roles'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.settings'],
            ['key' => 'branding', 'label' => 'Website & branding', 'icon' => 'image', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.branding'],
        ];
    }

    public static function href(array $item): string
    {
        if (($item['key'] ?? '') === 'bookings') {
            return route('ops.module', 'bookings');
        }

        return route($item['route']);
    }

    public static function active(array $item): bool
    {
        return match ($item['key']) {
            'dashboard' => request()->routeIs('district.dashboard'),
            'bookings' => request()->route('module') === 'bookings' || request()->routeIs('ops.bookings.show'),
            'drivers' => request()->routeIs('drivers.*'),
            'vehicles' => request()->routeIs('vehicles.*'),
            'fleets' => request()->routeIs('fleet-owners.*'),
            'franchises' => request()->routeIs('franchises.*'),
            'map' => request()->routeIs('live.map'),
            'wallets' => request()->routeIs('wallets.*') && ! request()->routeIs('wallets.commission*'),
            'commission' => request()->routeIs('wallets.commission*'),
            'reports' => request()->routeIs('reports.*'),
            'fleet-cut' => request()->routeIs('district.settings', 'district.fleet-cut'),
            'fare' => request()->routeIs('fare.*'),
            'roles' => request()->routeIs('ops.roles*'),
            'settings' => request()->routeIs('ops.settings*'),
            'branding' => request()->routeIs('ops.branding*'),
            default => false,
        };
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(?object $user = null): array
    {
        $user ??= auth()->user();
        if (! $user) {
            return [];
        }

        return NavAccess::grouped(self::items(), $user);
    }
}
