<?php

namespace App\Platform;

final class AdPolicy
{
    public const CATEGORIES = [
        'hotels' => 'Hotels',
        'restaurants' => 'Restaurants',
        'hospitals' => 'Hospitals',
        'coaching' => 'Coaching Institutes',
        'local_businesses' => 'Local Businesses',
        'automobile' => 'Automobile',
        'real_estate' => 'Real Estate',
        'travel' => 'Travel Businesses',
        'other' => 'Other approved businesses',
    ];

    public const TYPES = [
        'banner' => 'Banner',
        'listing' => 'Sponsored listing',
        'discovery' => 'Discovery card',
        'travel' => 'Travel promo',
    ];

    public const STATUSES = [
        'draft', 'pending', 'approved', 'published', 'paused', 'rejected', 'expired', 'completed',
    ];

    public const ALLOWED_PLACEMENTS = ['home', 'travel', 'discovery', 'catalog'];

    public const BLOCKED_PLACEMENTS = [
        'booking',
        'active_booking',
        'critical_booking',
        'otp',
        'payment',
        'wallet',
        'driver_arrival',
        'trip',
        'active_trip',
        'navigation',
        'driver_navigation',
        'sos',
        'live_trip',
    ];

    public const BANNER_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

    /**
     * @return array{ok: bool, reason: ?string}
     */
    public static function canServe(string $placement, ?string $suppressed = null): array
    {
        if ($suppressed) {
            return ['ok' => false, 'reason' => $suppressed];
        }
        if (in_array($placement, self::BLOCKED_PLACEMENTS, true)) {
            return ['ok' => false, 'reason' => 'blocked_placement'];
        }
        if (! in_array($placement, self::ALLOWED_PLACEMENTS, true)) {
            return ['ok' => false, 'reason' => 'unknown_placement'];
        }

        return ['ok' => true, 'reason' => null];
    }

    public static function matchesLocation(
        ?int $campaignStateId,
        ?int $campaignDistrictId,
        ?string $campaignCity,
        ?int $actorStateId,
        ?int $actorDistrictId,
        ?string $actorCity,
    ): bool {
        if ($campaignStateId !== null && ($actorStateId === null || $actorStateId !== $campaignStateId)) {
            return false;
        }
        if ($campaignDistrictId !== null && ($actorDistrictId === null || $actorDistrictId !== $campaignDistrictId)) {
            return false;
        }
        $want = strtolower(trim((string) $campaignCity));
        if ($want !== '') {
            $have = strtolower(trim((string) $actorCity));
            if ($have === '' || ! str_contains($have, $want)) {
                return false;
            }
        }

        return true;
    }

    public static function isLive(string $status, mixed $startsOn, mixed $endsOn, int $budgetPaise, int $budgetUsedPaise, ?\DateTimeInterface $now = null): bool
    {
        if ($status !== 'published') {
            return false;
        }
        $now = $now ?? now();
        $start = $startsOn ? \Illuminate\Support\Carbon::parse($startsOn) : null;
        $end = $endsOn ? \Illuminate\Support\Carbon::parse($endsOn) : null;
        if ($start && $now->lt($start)) {
            return false;
        }
        if ($end && $now->gt($end)) {
            return false;
        }

        return $budgetUsedPaise < $budgetPaise;
    }

    public static function ctr(int $impressions, int $clicks): float
    {
        if ($impressions <= 0) {
            return 0;
        }

        return round(($clicks * 10000) / $impressions) / 100;
    }

    public static function catalog(): array
    {
        return [
            'categories' => array_map(
                fn (string $key, string $title) => ['key' => $key, 'title' => $title],
                array_keys(self::CATEGORIES),
                array_values(self::CATEGORIES),
            ),
            'campaignTypes' => array_map(
                fn (string $key, string $title) => ['key' => $key, 'title' => $title],
                array_keys(self::TYPES),
                array_values(self::TYPES),
            ),
            'statuses' => self::STATUSES,
            'placements' => [
                'allowed' => self::ALLOWED_PLACEMENTS,
                'blocked' => self::BLOCKED_PLACEMENTS,
                'note' => 'Ads must never appear on an active ride, driver navigation, SOS, payment, OTP, or other critical booking actions. Use home, travel, discovery, or catalog only.',
            ],
            'metrics' => ['impressions', 'clicks', 'ctr', 'revenuePaise'],
        ];
    }
}
