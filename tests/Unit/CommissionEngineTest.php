<?php

namespace Tests\Unit;

use App\Platform\CommissionEngine;
use Tests\TestCase;

class CommissionEngineTest extends TestCase
{
    public function test_ten_percent_of_eligible_base_is_one_thousand_on_ten_thousand(): void
    {
        $settled = CommissionEngine::settle(
            ['basePaise' => 1_000_000] + $this->emptyOther(),
            ['percent' => 10, 'on_base_fare' => true],
            1_000_000,
        );

        $this->assertSame(1_000_000, $settled['eligiblePaise']);
        $this->assertSame(100_000, $settled['commissionPaise']);
        $this->assertSame(900_000, $settled['netPaise']);
    }

    public function test_toll_is_excluded_when_on_toll_is_false(): void
    {
        $off = CommissionEngine::settle(
            ['basePaise' => 800_000, 'tollPaise' => 200_000] + $this->emptyOther(),
            ['percent' => 10, 'on_base_fare' => true, 'on_toll' => false],
            1_000_000,
        );
        $on = CommissionEngine::settle(
            ['basePaise' => 800_000, 'tollPaise' => 200_000] + $this->emptyOther(),
            ['percent' => 10, 'on_base_fare' => true, 'on_toll' => true],
            1_000_000,
        );

        $this->assertSame(800_000, $off['eligiblePaise']);
        $this->assertSame(80_000, $off['commissionPaise']);
        $this->assertSame(1_000_000, $on['eligiblePaise']);
        $this->assertSame(100_000, $on['commissionPaise']);
    }

    public function test_complete_booking_amount_uses_total(): void
    {
        $settled = CommissionEngine::settle(
            ['basePaise' => 500_000, 'tollPaise' => 100_000] + $this->emptyOther(),
            ['percent' => 10, 'on_complete' => true],
            900_000,
        );

        $this->assertSame(900_000, $settled['eligiblePaise']);
        $this->assertSame(90_000, $settled['commissionPaise']);
        $this->assertSame(810_000, $settled['netPaise']);
    }

    public function test_snapshot_without_commission_does_not_invent_a_percent(): void
    {
        $settled = CommissionEngine::settleFromQuoteSnapshot(['totalPaise' => 10_000]);
        $this->assertSame(0, $settled['commissionPaise']);
        $this->assertSame(10_000, $settled['netPaise']);
        $this->assertNull($settled['percent']);
    }

    public function test_missing_percent_aborts_instead_of_hardcoding(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        CommissionEngine::settle(['basePaise' => 100], ['on_base_fare' => true], 100);
    }

    /**
     * @return array<string, int>
     */
    private function emptyOther(): array
    {
        return [
            'gstPaise' => 0,
            'tollPaise' => 0,
            'parkingPaise' => 0,
            'waitingPaise' => 0,
            'discountPaise' => 0,
            'otherPaise' => 0,
        ];
    }
}
