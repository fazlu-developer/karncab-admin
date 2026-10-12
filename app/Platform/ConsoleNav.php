<?php

namespace App\Platform;

final class ConsoleNav
{
    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(object $user): array
    {
        if (method_exists($user, 'isAdvertiser') && $user->isAdvertiser()) {
            return AdvertiserNav::grouped($user);
        }
        if (method_exists($user, 'isStateHead') && $user->isStateHead()) {
            return StateHeadNav::grouped($user);
        }
        if (method_exists($user, 'isFleetOwner') && $user->isFleetOwner()) {
            return FleetNav::grouped($user);
        }
        if (method_exists($user, 'isCustomer') && $user->isCustomer()) {
            return CustomerNav::grouped($user);
        }

        return OpsNav::grouped($user);
    }

    public static function href(object $user, array $item): string
    {
        if (method_exists($user, 'isAdvertiser') && $user->isAdvertiser()) {
            return AdvertiserNav::href($item);
        }
        if (method_exists($user, 'isStateHead') && $user->isStateHead()) {
            return StateHeadNav::href($item);
        }
        if (method_exists($user, 'isFleetOwner') && $user->isFleetOwner()) {
            return FleetNav::href($item);
        }
        if (method_exists($user, 'isCustomer') && $user->isCustomer()) {
            return CustomerNav::href($item);
        }

        return OpsNav::href($item);
    }

    public static function active(object $user, array $item): bool
    {
        if (method_exists($user, 'isAdvertiser') && $user->isAdvertiser()) {
            return AdvertiserNav::active($item);
        }
        if (method_exists($user, 'isStateHead') && $user->isStateHead()) {
            return StateHeadNav::active($item);
        }
        if (method_exists($user, 'isFleetOwner') && $user->isFleetOwner()) {
            return FleetNav::active($item);
        }
        if (method_exists($user, 'isCustomer') && $user->isCustomer()) {
            return CustomerNav::active($item);
        }

        return OpsNav::active($item);
    }
}
