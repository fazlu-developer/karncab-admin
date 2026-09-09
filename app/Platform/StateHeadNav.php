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
            ['key' => 'dashboard', 'label' => 'State dashboard', 'icon' => 'layout-dashboard', 'group' => 'Overview', 'route' => 'state.dashboard'],
            ['key' => 'districts', 'label' => 'Districts', 'icon' => 'map-pinned', 'group' => 'Network', 'route' => 'state.section'],
            ['key' => 'district-heads', 'label' => 'District Heads', 'icon' => 'user-cog', 'group' => 'Network', 'route' => 'state.section'],
            ['key' => 'franchises', 'label' => 'Franchises', 'icon' => 'store', 'group' => 'Network', 'route' => 'franchises.index'],
            ['key' => 'fleets', 'label' => 'Fleets', 'icon' => 'warehouse', 'group' => 'Network', 'route' => 'state.section'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Network', 'route' => 'state.section'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Network', 'route' => 'state.section'],
            ['key' => 'bookings', 'label' => 'Bookings', 'icon' => 'calendar-check', 'group' => 'Operations', 'route' => 'state.section'],
            ['key' => 'parcels', 'label' => 'Parcels', 'icon' => 'package', 'group' => 'Operations', 'route' => 'state.section'],
            ['key' => 'map', 'label' => 'Live fleet map', 'icon' => 'radar', 'group' => 'Operations', 'route' => 'live.map'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Operations', 'route' => 'state.section'],
            ['key' => 'revenue', 'label' => 'Revenue', 'icon' => 'banknote', 'group' => 'Finance', 'route' => 'state.section'],
            ['key' => 'wallets', 'label' => 'Wallets', 'icon' => 'wallet', 'group' => 'Finance', 'route' => 'wallets.index'],
            ['key' => 'commission', 'label' => 'Commission', 'icon' => 'percent', 'group' => 'Finance', 'route' => 'wallets.commission'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Finance', 'route' => 'reports.index'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'System', 'route' => 'state.section'],
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

        return request()->route('section') === $item['key'];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $groups = [];
        foreach (self::items() as $item) {
            $groups[$item['group']][] = $item;
        }

        return $groups;
    }
}
