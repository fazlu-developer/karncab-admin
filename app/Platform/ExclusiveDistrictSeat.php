<?php

namespace App\Platform;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /** District Head holds the exclusive district seat. Exclusive franchise role is retired. */
    public const KINDS = ['DISTRICT_HEAD'];

    public const TRANSITIONS = [
        'APPLIED' => ['UNDER_REVIEW', 'APPROVED', 'ACTIVE', 'TERMINATED'],
        'UNDER_REVIEW' => ['APPROVED', 'ACTIVE', 'TERMINATED'],
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

    public static function assertAvailable(int $districtId, ?int $exceptPlatformUserId = null): void
    {
        $platform = DB::connection('platform')->table('users')
            ->where('role', OperatorRole::DISTRICT_HEAD)
            ->where('district_id', $districtId)
            ->where('status', 'ACTIVE');
        if ($exceptPlatformUserId) {
            $platform->where('id', '!=', $exceptPlatformUserId);
        }
        abort_if($platform->exists(), 409, 'This district already has an active District Head.');

        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'district_id')) {
            return;
        }
        $email = $exceptPlatformUserId
            ? DB::connection('platform')->table('users')->where('id', $exceptPlatformUserId)->value('email')
            : null;
        $ops = User::query()
            ->where('role', OperatorRole::DISTRICT_HEAD)
            ->where('district_id', $districtId)
            ->where('status', 'ACTIVE');
        if (is_string($email) && $email !== '') {
            $ops->where('email', '!=', $email);
        }
        abort_if($ops->exists(), 409, 'This district already has an active District Head.');
    }
}
