<?php

namespace App\Platform;

final class CustomerNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, route: string, section?: string}>
     */
    public static function items(): array
    {
        $items = [
            ['key' => 'home', 'label' => 'Home', 'icon' => 'layout-dashboard', 'group' => 'Overview', 'route' => 'customer.dashboard'],
        ];
        foreach (CustomerDashboard::tools() as $tool) {
            $items[] = [
                'key' => $tool['key'],
                'label' => $tool['label'],
                'icon' => $tool['icon'],
                'group' => $tool['group'],
                'route' => 'customer.section',
                'section' => $tool['key'],
            ];
        }

        return $items;
    }

    public static function href(array $item): string
    {
        if (($item['route'] ?? '') === 'customer.section') {
            return route('customer.section', $item['section']);
        }

        return route($item['route']);
    }

    public static function active(array $item): bool
    {
        if (($item['key'] ?? '') === 'home') {
            return request()->routeIs('customer.dashboard');
        }

        return request()->route('section') === ($item['section'] ?? $item['key'] ?? null);
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
