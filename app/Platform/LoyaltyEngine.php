<?php

namespace App\Platform;

final class LoyaltyEngine
{
    /**
     * @param  array<string, mixed>  $context  Must include server pointsBalance. Client points/discount are ignored.
     * @return array{ok: bool, reason: ?string, discountPaise: int, pointsUsed: int}
     */
    public static function quote(array $context): array
    {
        unset($context['discountPaise'], $context['discount_paise'], $context['points']);
        $fare = max(0, (int) ($context['farePaise'] ?? 0));
        $balance = max(0, (int) ($context['pointsBalance'] ?? 0));
        $paisePerPoint = max(0, (int) ($context['paisePerPoint'] ?? 100));
        $maxPaise = max(0, (int) ($context['maxRedeemPaise'] ?? 0));
        if ($balance <= 0 || $paisePerPoint <= 0 || $fare <= 0) {
            return ['ok' => false, 'reason' => 'no_points', 'discountPaise' => 0, 'pointsUsed' => 0];
        }
        $raw = $balance * $paisePerPoint;
        if ($maxPaise > 0) {
            $raw = min($raw, $maxPaise);
        }
        $discount = min($fare, $raw);
        $pointsUsed = (int) floor($discount / $paisePerPoint);

        return [
            'ok' => $discount > 0,
            'reason' => $discount > 0 ? null : 'no_points',
            'discountPaise' => $discount,
            'pointsUsed' => $pointsUsed,
        ];
    }
}
