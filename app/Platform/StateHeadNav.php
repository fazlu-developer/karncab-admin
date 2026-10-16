<?php

namespace App\Platform;

final class StateHeadNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, route: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'State dashboard', 'icon' => 'layout-dashboard', 'group' => 'Overview', 'ability' => 'dashboard.view', 'route' => 'state.dashboard'],
            ['key' => 'districts', 'label' => 'Districts', 'icon' => 'map-pinned', 'group' => 'Network', 'ability' => 'district.view', 'route' => 'state.section'],
            ['key' => 'district-heads', 'label' => 'District Heads', 'icon' => 'user-cog', 'group' => 'Network', 'ability' => 'manager.view', 'route' => 'state.section'],
            ['key' => 'franchises', 'label' => 'Franchises', 'icon' => 'store', 'group' => 'Network', 'ability' => 'franchise.view', 'route' => 'franchises.index'],
            ['key' => 'fleets', 'label' => 'Fleets', 'icon' => 'warehouse', 'group' => 'Network', 'ability' => 'fleet.view', 'route' => 'state.section'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Network', 'ability' => 'drivers.view', 'route' => 'state.section'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Network', 'ability' => 'vehicles.view', 'route' => 'vehicles.index'],
            ['key' => 'district-vehicles', 'label' => 'District vehicles', 'icon' => 'map', 'group' => 'Network', 'ability' => 'vehicles.view', 'route' => 'vehicles.districts'],
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Operations', 'ability' => 'bookings.view', 'route' => 'state.section'],
            ['key' => 'parcels', 'label' => 'Parcels', 'icon' => 'package', 'group' => 'Operations', 'ability' => 'parcels.view', 'route' => 'state.section'],
            ['key' => 'map', 'label' => 'Live fleet map', 'icon' => 'radar', 'group' => 'Operations', 'ability' => 'tracking.view', 'route' => 'live.map'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Operations', 'ability' => 'complaints.manage', 'route' => 'state.section'],
            ['key' => 'revenue', 'label' => 'Revenue', 'icon' => 'banknote', 'group' => 'Finance', 'ability' => 'reports.view', 'route' => 'state.section'],
            ['key' => 'wallets', 'label' => 'Wallets', 'icon' => 'wallet', 'group' => 'Finance', 'ability' => 'wallet.view', 'route' => 'wallets.index'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'ability' => 'commission.view', 'route' => 'wallets.commission'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Finance', 'ability' => 'reports.view', 'route' => 'reports.index'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'System', 'ability' => 'notifications.view', 'route' => 'state.section'],
            ['key' => 'fare', 'label' => 'Fare Management', 'icon' => 'banknote', 'group' => 'System', 'ability' => 'fare.manage', 'route' => 'fare.index'],
            ['key' => 'roles', 'label' => 'Roles', 'icon' => 'key-round', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.roles'],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.settings'],
            ['key' => 'branding', 'label' => 'Website & branding', 'icon' => 'image', 'group' => 'System', 'ability' => 'platform.admin', 'route' => 'ops.branding'],
        ];
    }

    public static function href(array $item): string
    {
        if ($item['key'] === 'dashboard') {
            return route('state.dashboard');
        }
        if ($item['key'] === 'franchises') {
            return route('franchises.index');
        }
        if ($item['key'] === 'vehicles') {
            return route('vehicles.index');
        }
        if ($item['key'] === 'district-vehicles') {
            return route('vehicles.districts');
        }
        if ($item['key'] === 'map') {
            return route('live.map');
        }
        if ($item['key'] === 'wallets') {
            return route('wallets.index');
        }
        if ($item['key'] === 'commission') {
            return route('wallets.commission');
        }
        if ($item['key'] === 'reports') {
            return route('reports.index');
        }
        if (in_array($item['key'], ['fare', 'roles', 'settings', 'branding'], true) && ! empty($item['route'])) {
            return route($item['route']);
        }

        return route('state.section', $item['key']);
    }

    public static function active(array $item): bool
    {
        if ($item['key'] === 'dashboard') {
            return request()->routeIs('state.dashboard');
        }
        if ($item['key'] === 'franchises') {
            return request()->routeIs('franchises.*');
        }
        if ($item['key'] === 'vehicles') {
            return request()->routeIs('vehicles.*') && ! request()->routeIs('vehicles.districts');
        }
        if ($item['key'] === 'district-vehicles') {
            return request()->routeIs('vehicles.districts');
        }
        if ($item['key'] === 'map') {
            return request()->routeIs('live.map');
        }
        if ($item['key'] === 'wallets') {
            return request()->routeIs('wallets.*') && ! request()->routeIs('wallets.commission*');
        }
        if ($item['key'] === 'commission') {
            return request()->routeIs('wallets.commission*');
        }
        if ($item['key'] === 'reports') {
            return request()->routeIs('reports.*');
        }
        if ($item['key'] === 'fare') {
            return request()->routeIs('fare.*');
        }
        if ($item['key'] === 'roles') {
            return request()->routeIs('ops.roles*');
        }
        if ($item['key'] === 'settings') {
            return request()->routeIs('ops.settings*');
        }
        if ($item['key'] === 'branding') {
            return request()->routeIs('ops.branding*');
        }

        return request()->route('section') === $item['key'];
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
