<?php

namespace App\Platform;

final class AdvertiserNav
{
    /**
     * @return list<array{key: string, label: string, icon: string, group: string, route: string}>
     */
    public static function items(): array
    {
        return [
            ['key' => 'ads', 'label' => 'Campaigns', 'icon' => 'megaphone', 'group' => 'Advertising', 'ability' => 'advertising.view', 'route' => 'ads.index'],
        ];
    }

    public static function href(array $item): string
    {
        return route($item['route']);
    }

    public static function active(array $item): bool
    {
        return request()->routeIs('ads.*');
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
