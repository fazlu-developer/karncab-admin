<?php

namespace App\Platform;

final class NavAccess
{
    public static function allowed(?object $user, ?string $ability): bool
    {
        if ($ability === null || $ability === '') {
            return false;
        }
        if ($user && method_exists($user, 'can')) {
            return (bool) $user->can($ability);
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(array $items, object $user): array
    {
        $groups = [];
        foreach ($items as $item) {
            if (! self::allowed($user, $item['ability'] ?? null)) {
                continue;
            }
            $groups[$item['group']][] = $item;
        }

        return $groups;
    }
}
