<?php

namespace App\Platform;

final class OperatorRole
{
    public const CUSTOMER = 'CUSTOMER';
    public const DRIVER = 'DRIVER';
    public const FLEET_OWNER = 'FLEET_OWNER';
    public const DISTRICT_HEAD = 'DISTRICT_HEAD';
    public const STATE_HEAD = 'STATE_HEAD';
    /** @deprecated Use DISTRICT_HEAD. Kept so old rows still load. */
    public const FRANCHISE = 'FRANCHISE';
    public const ADMIN = 'ADMIN';
    public const MANAGER = 'MANAGER';
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    /** @deprecated Treat as CUSTOMER. */
    public const CORPORATE = 'CORPORATE';
    /** @deprecated Not a console role. */
    public const ADVERTISER = 'ADVERTISER';
    public const PENDING = 'PENDING';

    /**
     * Roles the management console creates and manages.
     *
     * @return list<string>
     */
    public static function managed(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::MANAGER,
            self::STATE_HEAD,
            self::DISTRICT_HEAD,
            self::FLEET_OWNER,
            self::DRIVER,
            self::CUSTOMER,
        ];
    }

    /**
     * Platform operators who get an admin-panel login.
     *
     * @return list<string>
     */
    public static function consoleRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::MANAGER,
            self::STATE_HEAD,
            self::DISTRICT_HEAD,
            self::FLEET_OWNER,
        ];
    }

    /** @var list<string> */
    public const OPERATOR_ROLES = [
        self::FLEET_OWNER,
        self::DISTRICT_HEAD,
        self::STATE_HEAD,
        self::FRANCHISE,
        self::ADMIN,
        self::MANAGER,
        self::SUPER_ADMIN,
        self::CORPORATE,
        self::ADVERTISER,
        self::PENDING,
    ];

    /**
     * Normalize legacy roles onto the managed set.
     */
    public static function normalize(string $role): string
    {
        return match ($role) {
            self::FRANCHISE => self::DISTRICT_HEAD,
            self::CORPORATE => self::CUSTOMER,
            self::ADVERTISER => self::CUSTOMER,
            self::PENDING => self::CUSTOMER,
            default => $role,
        };
    }

    public static function isManaged(string $role): bool
    {
        return in_array(self::normalize($role), self::managed(), true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CUSTOMER,
            self::DRIVER,
            ...self::OPERATOR_ROLES,
        ];
    }
}
