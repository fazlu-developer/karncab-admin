<?php

namespace App\Platform;

final class CouponEngine
{
    public const KINDS = ['percent', 'fixed'];

    public const AUDIENCES = [
        'all' => 'Everyone',
        'new_user' => 'New user',
        'existing_user' => 'Existing user',
        'first_ride' => 'First ride',
        'corporate' => 'Corporate',
    ];

    public const PRODUCTS = [
        'LOCAL_CAB', 'ONE_WAY', 'ROUND_WAY', 'RENTAL', 'SCHEDULE',
        'OUTSTATION', 'AIRPORT', 'RAILWAY', 'MULTI_STOP',
    ];

    /**
     * Quote a discount from the stored coupon only. Client discount amounts are ignored.
     *
     * @param  array<string, mixed>  $coupon
     * @param  array<string, mixed>  $context
     * @return array{ok: bool, reason: ?string, discountPaise: int, code: ?string, title: ?string}
     */
    public static function quote(array $coupon, array $context): array
    {
        unset($context['discountPaise'], $context['discount_paise'], $context['couponDiscountPaise']);

        $code = strtoupper(trim((string) ($coupon['code'] ?? '')));
        $empty = [
            'ok' => false,
            'reason' => null,
            'discountPaise' => 0,
            'code' => $code ?: null,
            'title' => $coupon['title'] ?? null,
        ];

        if (! ($coupon['active'] ?? false)) {
            return ['ok' => false, 'reason' => 'inactive', 'discountPaise' => 0, 'code' => $code ?: null, 'title' => $coupon['title'] ?? null];
        }

        $now = $context['now'] ?? now();
        $starts = isset($coupon['starts_on']) ? \Illuminate\Support\Carbon::parse($coupon['starts_on']) : null;
        $ends = isset($coupon['ends_on']) ? \Illuminate\Support\Carbon::parse($coupon['ends_on']) : null;
        if ($starts && $now->lt($starts)) {
            return array_merge($empty, ['reason' => 'not_started']);
        }
        if ($ends && $now->gt($ends)) {
            return array_merge($empty, ['reason' => 'expired']);
        }

        $fare = max(0, (int) ($context['farePaise'] ?? 0));
        $minFare = (int) ($coupon['min_fare_paise'] ?? 0);
        if ($minFare > 0 && $fare < $minFare) {
            return array_merge($empty, ['reason' => 'min_fare']);
        }

        $product = $coupon['product'] ?? null;
        if ($product && ($context['product'] ?? null) !== $product) {
            return array_merge($empty, ['reason' => 'service']);
        }

        $stateId = $coupon['state_id'] ?? null;
        if ($stateId !== null && (int) ($context['stateId'] ?? 0) !== (int) $stateId) {
            return array_merge($empty, ['reason' => 'state']);
        }

        $districtId = $coupon['district_id'] ?? null;
        if ($districtId !== null && (int) ($context['districtId'] ?? 0) !== (int) $districtId) {
            return array_merge($empty, ['reason' => 'district']);
        }

        $audience = $coupon['audience'] ?? 'all';
        $audienceFail = self::audienceReason($audience, $context);
        if ($audienceFail) {
            return array_merge($empty, ['reason' => $audienceFail]);
        }

        $usageLimit = (int) ($coupon['usage_limit'] ?? 0);
        if ($usageLimit > 0 && (int) ($context['usageCount'] ?? 0) >= $usageLimit) {
            return array_merge($empty, ['reason' => 'usage_limit']);
        }

        $userLimit = (int) ($coupon['user_limit'] ?? 0);
        if ($userLimit > 0 && (int) ($context['userUsageCount'] ?? 0) >= $userLimit) {
            return array_merge($empty, ['reason' => 'user_limit']);
        }

        $discount = self::discountPaise($coupon, $fare);
        if ($discount <= 0) {
            return array_merge($empty, ['reason' => 'no_discount']);
        }

        return [
            'ok' => true,
            'reason' => null,
            'discountPaise' => $discount,
            'code' => $code,
            'title' => $coupon['title'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $coupon
     */
    public static function discountPaise(array $coupon, int $farePaise): int
    {
        $farePaise = max(0, $farePaise);
        $kind = $coupon['kind'] ?? ((int) ($coupon['percent'] ?? 0) > 0 ? 'percent' : 'fixed');
        $raw = 0;
        if ($kind === 'percent') {
            $percent = max(0, min(100, (int) ($coupon['percent'] ?? 0)));
            $raw = (int) floor(($farePaise * $percent) / 100);
        } else {
            $raw = max(0, (int) ($coupon['amount_paise'] ?? 0));
        }
        $max = (int) ($coupon['max_discount_paise'] ?? 0);
        if ($max > 0) {
            $raw = min($raw, $max);
        }

        return min($farePaise, $raw);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function audienceReason(string $audience, array $context): ?string
    {
        $bookings = (int) ($context['bookingCount'] ?? 0);
        $completed = (int) ($context['completedRides'] ?? 0);
        $role = (string) ($context['role'] ?? '');

        return match ($audience) {
            'all', '' => null,
            'new_user' => $bookings === 0 ? null : 'new_user',
            'existing_user' => $completed > 0 ? null : 'existing_user',
            'first_ride' => $completed === 0 ? null : 'first_ride',
            'corporate' => $role === OperatorRole::CORPORATE ? null : 'corporate',
            default => 'audience',
        };
    }

    public static function message(?string $reason): string
    {
        return match ($reason) {
            'inactive', 'not_started', 'expired' => 'This coupon is not valid',
            'min_fare' => 'Booking amount is below the coupon minimum',
            'service' => 'This coupon is not valid for this service',
            'state' => 'This coupon is not valid in your state',
            'district' => 'This coupon is not valid in your district',
            'new_user' => 'This coupon is for new users only',
            'existing_user' => 'This coupon is for existing users only',
            'first_ride' => 'This coupon is for a first ride only',
            'corporate' => 'This coupon is for corporate accounts only',
            'usage_limit' => 'This coupon has reached its usage limit',
            'user_limit' => 'You have already used this coupon',
            'no_discount' => 'This coupon does not reduce this fare',
            default => 'This coupon is not valid',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        return [
            'kinds' => self::KINDS,
            'audiences' => array_map(
                fn (string $key, string $title) => ['key' => $key, 'title' => $title],
                array_keys(self::AUDIENCES),
                array_values(self::AUDIENCES),
            ),
            'products' => self::PRODUCTS,
            'note' => 'Coupons are quoted on the server. Discount amounts from the app are ignored.',
        ];
    }
}
