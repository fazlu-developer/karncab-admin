<?php

namespace App\Platform;

/**
 * Server-side commission math. Percent and eligible buckets always come from a
 * stored commission_rules row (or an explicit rule array). Settlement code must
 * not invent a percentage when the rule is missing.
 */
final class CommissionEngine
{
    public const POLICY_NOTE = 'Commission is calculated only on the server from this rule. Apps must display quote.commission and wallet ledger rows and must not recompute splits.';

    /**
     * @return array{basePaise: int, gstPaise: int, tollPaise: int, parkingPaise: int, waitingPaise: int, discountPaise: int, otherPaise: int}
     */
    public static function emptyBuckets(): array
    {
        return [
            'basePaise' => 0,
            'gstPaise' => 0,
            'tollPaise' => 0,
            'parkingPaise' => 0,
            'waitingPaise' => 0,
            'discountPaise' => 0,
            'otherPaise' => 0,
        ];
    }

    /**
     * @param  object|array<string, mixed>|null  $rule
     * @return array<string, mixed>
     */
    public static function flags(object|array|null $rule): array
    {
        $row = self::asArray($rule);

        return [
            'percent' => self::percentFromRule($row),
            'onBaseFare' => self::flag($row, ['onBaseFare', 'on_base_fare'], true),
            'onGst' => self::flag($row, ['onGst', 'on_gst'], false),
            'onToll' => self::flag($row, ['onToll', 'on_toll'], false),
            'onParking' => self::flag($row, ['onParking', 'on_parking'], false),
            'onWaiting' => self::flag($row, ['onWaiting', 'on_waiting'], false),
            'onDiscount' => self::flag($row, ['onDiscount', 'on_discount'], false),
            'onOther' => self::flag($row, ['onOther', 'on_other'], false),
            'onComplete' => self::flag($row, ['onComplete', 'on_complete'], false),
        ];
    }

    /**
     * @param  object|array<string, mixed>|null  $rule
     * @return array<string, mixed>
     */
    public static function serializePolicy(object|array|null $rule): array
    {
        $flags = self::flags($rule);

        return [
            'percent' => $flags['percent'],
            'onBaseFare' => $flags['onBaseFare'],
            'onGst' => $flags['onGst'],
            'onToll' => $flags['onToll'],
            'onParking' => $flags['onParking'],
            'onWaiting' => $flags['onWaiting'],
            'onDiscount' => $flags['onDiscount'],
            'onOther' => $flags['onOther'],
            'onComplete' => $flags['onComplete'],
            'appliesTo' => [
                'baseFare' => $flags['onBaseFare'],
                'gst' => $flags['onGst'],
                'toll' => $flags['onToll'],
                'parking' => $flags['onParking'],
                'waiting' => $flags['onWaiting'],
                'discount' => $flags['onDiscount'],
                'otherCharges' => $flags['onOther'],
                'completeBookingAmount' => $flags['onComplete'],
            ],
            'source' => 'server',
            'note' => self::POLICY_NOTE,
        ];
    }

    /**
     * @param  array<string, mixed>  $buckets
     * @param  object|array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    public static function settle(array $buckets, object|array $rule, int $totalPaise): array
    {
        $flags = self::flags($rule);
        $percent = $flags['percent'];
        if ($percent === null) {
            abort(422, 'Commission percent is not configured.');
        }

        $merged = array_merge(self::emptyBuckets(), $buckets);
        if ($flags['onComplete']) {
            $eligible = max(0, $totalPaise);
            $applied = self::emptyBuckets();

            return self::result((float) $percent, $eligible, $totalPaise, $merged, $applied);
        }

        $applied = [
            'basePaise' => $flags['onBaseFare'] ? (int) $merged['basePaise'] : 0,
            'gstPaise' => $flags['onGst'] ? (int) $merged['gstPaise'] : 0,
            'tollPaise' => $flags['onToll'] ? (int) $merged['tollPaise'] : 0,
            'parkingPaise' => $flags['onParking'] ? (int) $merged['parkingPaise'] : 0,
            'waitingPaise' => $flags['onWaiting'] ? (int) $merged['waitingPaise'] : 0,
            'discountPaise' => $flags['onDiscount'] ? (int) $merged['discountPaise'] : 0,
            'otherPaise' => $flags['onOther'] ? (int) $merged['otherPaise'] : 0,
        ];
        $eligible = array_sum($applied);

        return self::result((float) $percent, (int) $eligible, $totalPaise, $merged, $applied);
    }

    /**
     * Replay a stored quote. Does not invent commission when the snapshot has none.
     *
     * @return array<string, mixed>
     */
    public static function settleFromQuoteSnapshot(mixed $snapshot, int $fallbackTotalPaise = 0): array
    {
        $row = is_array($snapshot) ? $snapshot : (is_object($snapshot) ? (array) $snapshot : []);
        if (is_string($snapshot) && $snapshot !== '') {
            $decoded = json_decode($snapshot, true);
            $row = is_array($decoded) ? $decoded : [];
        }
        $totalPaise = (int) ($row['totalPaise'] ?? $fallbackTotalPaise);
        $stored = $row['commission'] ?? null;
        if (is_object($stored)) {
            $stored = (array) $stored;
        }
        if (is_array($stored) && is_numeric($stored['commissionPaise'] ?? null)) {
            $commissionPaise = (int) $stored['commissionPaise'];
            $buckets = self::asBuckets($stored['buckets'] ?? []);
            $percent = isset($stored['percent']) && is_numeric($stored['percent']) ? (float) $stored['percent'] : null;

            return [
                'percent' => $percent,
                'eligiblePaise' => (int) ($stored['eligiblePaise'] ?? 0),
                'commissionPaise' => $commissionPaise,
                'netPaise' => isset($stored['netPaise']) && is_numeric($stored['netPaise'])
                    ? (int) $stored['netPaise']
                    : max(0, $totalPaise - $commissionPaise),
                'buckets' => $buckets,
                'applied' => isset($stored['applied']) ? self::asBuckets($stored['applied']) : $buckets,
            ];
        }
        if (isset($row['breakdown']) && (is_array($row['breakdown']) || is_object($row['breakdown']))) {
            return [
                'percent' => null,
                'eligiblePaise' => 0,
                'commissionPaise' => 0,
                'netPaise' => $totalPaise,
                'buckets' => self::bucketsFromBreakdown(self::asArray($row['breakdown'])),
                'applied' => self::emptyBuckets(),
                'fromBreakdown' => true,
            ];
        }
        if (isset($row['commissionPaise']) && is_numeric($row['commissionPaise'])) {
            $commissionPaise = (int) $row['commissionPaise'];

            return [
                'percent' => isset($row['commissionPercent']) && is_numeric($row['commissionPercent']) ? (float) $row['commissionPercent'] : null,
                'eligiblePaise' => 0,
                'commissionPaise' => $commissionPaise,
                'netPaise' => max(0, $totalPaise - $commissionPaise),
                'buckets' => self::emptyBuckets(),
                'applied' => self::emptyBuckets(),
            ];
        }

        return [
            'percent' => null,
            'eligiblePaise' => 0,
            'commissionPaise' => 0,
            'netPaise' => $totalPaise,
            'buckets' => self::emptyBuckets(),
            'applied' => self::emptyBuckets(),
        ];
    }

    /**
     * @param  array<string, mixed>  $breakdown
     * @return array<string, int>
     */
    public static function bucketsFromBreakdown(array $breakdown): array
    {
        return [
            'basePaise' => (int) ($breakdown['basePaise'] ?? 0) + (int) ($breakdown['distancePaise'] ?? 0) + (int) ($breakdown['extraPaise'] ?? 0),
            'gstPaise' => (int) ($breakdown['gstPaise'] ?? 0),
            'tollPaise' => (int) ($breakdown['tollPaise'] ?? 0),
            'parkingPaise' => (int) ($breakdown['parkingPaise'] ?? 0),
            'waitingPaise' => (int) ($breakdown['waitingPaise'] ?? 0),
            'discountPaise' => (int) ($breakdown['discountPaise'] ?? 0),
            'otherPaise' => (int) ($breakdown['nightPaise'] ?? 0)
                + (int) ($breakdown['driverAllowPaise'] ?? 0)
                + (int) ($breakdown['nightStayPaise'] ?? 0)
                + (int) ($breakdown['rentalExtraPaise'] ?? 0)
                + (int) ($breakdown['stopPaise'] ?? 0),
        ];
    }

    /**
     * @param  array{fleetPercent: float|int, territoryPercent: float|int}  $shares
     * @return array{fleetPaise: int, territoryPaise: int, unallocatedPaise: int}
     */
    public static function splitPlatformCommission(int $commissionPaise, array $shares): array
    {
        $commission = max(0, $commissionPaise);
        $fleetPct = min(100, max(0, (float) ($shares['fleetPercent'] ?? 0)));
        $territoryPct = min(100, max(0, (float) ($shares['territoryPercent'] ?? 0)));
        $fleetPaise = (int) round(($commission * $fleetPct) / 100);
        $territoryPaise = (int) round(($commission * $territoryPct) / 100);
        if ($fleetPaise + $territoryPaise > $commission) {
            $totalPct = $fleetPct + $territoryPct;
            $fleetPaise = $totalPct > 0 ? (int) round(($commission * $fleetPct) / $totalPct) : 0;
            $territoryPaise = $commission - $fleetPaise;
        }

        return [
            'fleetPaise' => $fleetPaise,
            'territoryPaise' => $territoryPaise,
            'unallocatedPaise' => max(0, $commission - $fleetPaise - $territoryPaise),
        ];
    }

    /**
     * @param  object|array<string, mixed>|null  $rule
     */
    public static function percentFromRule(object|array|null $rule): ?float
    {
        $row = self::asArray($rule);
        if (! isset($row['percent']) || ! is_numeric($row['percent'])) {
            return null;
        }

        return (float) $row['percent'];
    }

    /**
     * @param  array<string, mixed>  $buckets
     * @param  array<string, int>  $applied
     * @return array<string, mixed>
     */
    private static function result(float $percent, int $eligiblePaise, int $totalPaise, array $buckets, array $applied): array
    {
        $commissionPaise = (int) round(($eligiblePaise * $percent) / 100);

        return [
            'percent' => $percent,
            'eligiblePaise' => $eligiblePaise,
            'commissionPaise' => $commissionPaise,
            'netPaise' => max(0, $totalPaise - $commissionPaise),
            'buckets' => $buckets,
            'applied' => $applied,
        ];
    }

    /**
     * @param  mixed  $value
     * @return array<string, int>
     */
    private static function asBuckets(mixed $value): array
    {
        $row = self::asArray($value);

        return [
            'basePaise' => (int) ($row['basePaise'] ?? 0),
            'gstPaise' => (int) ($row['gstPaise'] ?? 0),
            'tollPaise' => (int) ($row['tollPaise'] ?? 0),
            'parkingPaise' => (int) ($row['parkingPaise'] ?? 0),
            'waitingPaise' => (int) ($row['waitingPaise'] ?? 0),
            'discountPaise' => (int) ($row['discountPaise'] ?? 0),
            'otherPaise' => (int) ($row['otherPaise'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function flag(array $row, array $keys, bool $default): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null) {
                return filter_var($row[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private static function asArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_object($value)) {
            return get_object_vars($value);
        }

        return [];
    }
}
