<?php

namespace App\Platform;

final class OperatorRole
{
    public const CUSTOMER = 'CUSTOMER';
    public const DRIVER = 'DRIVER';
    public const FLEET_OWNER = 'FLEET_OWNER';
    public const DISTRICT_HEAD = 'DISTRICT_HEAD';
    public const STATE_HEAD = 'STATE_HEAD';
    public const FRANCHISE = 'FRANCHISE';
    public const ADMIN = 'ADMIN';
    public const SUPER_ADMIN = 'SUPER_ADMIN';
    public const CORPORATE = 'CORPORATE';
    public const ADVERTISER = 'ADVERTISER';
    public const PENDING = 'PENDING';

    public const OPERATOR_ROLES = [
        self::FLEET_OWNER,
        self::DISTRICT_HEAD,
        self::STATE_HEAD,
        self::FRANCHISE,
        self::ADMIN,
        self::SUPER_ADMIN,
        self::CORPORATE,
        self::ADVERTISER,
        self::PENDING,
    ];

    public static function all(): array
    {
        return [
            self::CUSTOMER,
            self::DRIVER,
            ...self::OPERATOR_ROLES,
        ];
    }
}
