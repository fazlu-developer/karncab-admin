<?php

namespace App\Platform;

final class ExclusiveDistrictSeat
{
    public const STATUSES = [
        'APPLIED',
        'UNDER_REVIEW',
        'APPROVED',
        'ACTIVE',
        'SUSPENDED',
        'EXPIRED',
        'TERMINATED',
    ];

    public const KINDS = ['DISTRICT_HEAD', 'EXCLUSIVE_FRANCHISE'];

    public const TRANSITIONS = [
        'APPLIED' => ['UNDER_REVIEW', 'TERMINATED'],
        'UNDER_REVIEW' => ['APPROVED', 'TERMINATED'],
        'APPROVED' => ['ACTIVE', 'TERMINATED'],
        'ACTIVE' => ['SUSPENDED', 'EXPIRED', 'TERMINATED'],
        'SUSPENDED' => ['ACTIVE', 'TERMINATED'],
        'EXPIRED' => ['ACTIVE', 'TERMINATED'],
        'TERMINATED' => [],
    ];

    public static function key(int $districtId): string
    {
        return (string) $districtId;
    }

    public static function holdsSeat(string $status): bool
    {
        return $status === 'ACTIVE';
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
