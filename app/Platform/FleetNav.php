<?php

namespace App\Platform;

final class FleetNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, route: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Fleet home', 'icon' => 'warehouse', 'group' => 'Overview', 'route' => 'fleet.dashboard'],
            ['key' => 'vehicles', 'label' => 'Vehicles', 'icon' => 'car', 'group' => 'Fleet', 'route' => 'fleet.vehicles'],
            ['key' => 'drivers', 'label' => 'Drivers', 'icon' => 'id-card', 'group' => 'Fleet', 'route' => 'fleet.drivers'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart-column', 'group' => 'Fleet', 'route' => 'fleet.reports'],
            ['key' => 'wallets', 'label' => 'Wallets', 'icon' => 'wallet', 'group' => 'Fleet', 'route' => 'wallets.index'],
            ['key' => 'map', 'label' => 'Live map', 'icon' => 'radar', 'group' => 'Fleet', 'route' => 'live.map'],
        ];
    }

    public static function href(array $item): string
    {
        return route($item['route']);
    }

    public static function active(array $item): bool
    {
        return match ($item['key']) {
            'dashboard' => request()->routeIs('fleet.dashboard'),
            'vehicles' => request()->routeIs('fleet.vehicles*') || request()->routeIs('fleet.vehicle*'),
            'drivers' => request()->routeIs('fleet.drivers*') || request()->routeIs('fleet.driver*'),
            'reports' => request()->routeIs('fleet.reports') || request()->routeIs('fleet.trips*') || request()->routeIs('reports.*'),
            'wallets' => request()->routeIs('wallets.*'),
            'map' => request()->routeIs('live.map'),
            default => false,
        };
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
