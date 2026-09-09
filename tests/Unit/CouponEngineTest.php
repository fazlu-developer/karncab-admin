<?php

namespace Tests\Unit;

use App\Platform\CouponEngine;
use App\Platform\LoyaltyEngine;
use App\Platform\OperatorRole;
use Tests\TestCase;

class CouponEngineTest extends TestCase
{
    public function test_percent_is_capped_by_max_and_fare(): void
    {
        $coupon = $this->coupon(['kind' => 'percent', 'percent' => 50, 'max_discount_paise' => 1000]);
        $quoted = CouponEngine::quote($coupon, ['farePaise' => 10_000]);
        $this->assertTrue($quoted['ok']);
        $this->assertSame(1000, $quoted['discountPaise']);
    }

    public function test_fixed_discount_does_not_use_client_amount(): void
    {
        $coupon = $this->coupon(['kind' => 'fixed', 'amount_paise' => 5000, 'percent' => 0]);
        $quoted = CouponEngine::quote($coupon, [
            'farePaise' => 20_000,
            'discountPaise' => 19_000,
        ]);
        $this->assertSame(5000, $quoted['discountPaise']);
    }

    public function test_rules_for_min_fare_service_territory_audience_and_limits(): void
    {
        $base = $this->coupon(['min_fare_paise' => 10_000, 'product' => 'AIRPORT', 'state_id' => 1, 'district_id' => 10]);
        $this->assertSame('min_fare', CouponEngine::quote($base, ['farePaise' => 100, 'product' => 'AIRPORT', 'stateId' => 1, 'districtId' => 10])['reason']);
        $this->assertSame('service', CouponEngine::quote($base, ['farePaise' => 20_000, 'product' => 'LOCAL_CAB', 'stateId' => 1, 'districtId' => 10])['reason']);
        $this->assertSame('state', CouponEngine::quote($base, ['farePaise' => 20_000, 'product' => 'AIRPORT', 'stateId' => 2, 'districtId' => 10])['reason']);
        $this->assertSame('district', CouponEngine::quote($base, ['farePaise' => 20_000, 'product' => 'AIRPORT', 'stateId' => 1, 'districtId' => 99])['reason']);

        $first = $this->coupon(['audience' => 'first_ride']);
        $this->assertTrue(CouponEngine::quote($first, ['farePaise' => 20_000, 'completedRides' => 0])['ok']);
        $this->assertSame('first_ride', CouponEngine::quote($first, ['farePaise' => 20_000, 'completedRides' => 1])['reason']);

        $corp = $this->coupon(['audience' => 'corporate']);
        $this->assertSame('corporate', CouponEngine::quote($corp, ['farePaise' => 20_000, 'role' => OperatorRole::CUSTOMER])['reason']);
        $this->assertTrue(CouponEngine::quote($corp, ['farePaise' => 20_000, 'role' => OperatorRole::CORPORATE])['ok']);

        $limited = $this->coupon(['usage_limit' => 1, 'user_limit' => 1]);
        $this->assertSame('usage_limit', CouponEngine::quote($limited, ['farePaise' => 20_000, 'usageCount' => 1])['reason']);
        $this->assertSame('user_limit', CouponEngine::quote($limited, ['farePaise' => 20_000, 'userUsageCount' => 1])['reason']);
        $this->assertSame('expired', CouponEngine::quote($this->coupon(['ends_on' => '2020-01-01']), ['farePaise' => 20_000, 'now' => now()])['reason']);
    }

    public function test_loyalty_quote_uses_server_balance_not_client_points(): void
    {
        $quoted = LoyaltyEngine::quote([
            'farePaise' => 5000,
            'pointsBalance' => 20,
            'paisePerPoint' => 100,
            'points' => 9999,
            'discountPaise' => 4000,
        ]);
        $this->assertTrue($quoted['ok']);
        $this->assertSame(2000, $quoted['discountPaise']);
        $this->assertSame(20, $quoted['pointsUsed']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function coupon(array $overrides = []): array
    {
        return array_merge([
            'code' => 'SAVE10',
            'title' => 'Save',
            'kind' => 'percent',
            'percent' => 10,
            'amount_paise' => 0,
            'max_discount_paise' => 0,
            'min_fare_paise' => 0,
            'active' => true,
            'starts_on' => now()->subDay(),
            'ends_on' => now()->addMonth(),
            'audience' => 'all',
        ], $overrides);
    }
}
