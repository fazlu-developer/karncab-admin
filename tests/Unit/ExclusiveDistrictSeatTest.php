<?php

namespace Tests\Unit;

use App\Platform\ExclusiveDistrictSeat;
use PHPUnit\Framework\TestCase;

class ExclusiveDistrictSeatTest extends TestCase
{
    public function test_only_active_holds_the_district_seat(): void
    {
        $this->assertTrue(ExclusiveDistrictSeat::holdsSeat('ACTIVE'));
        $this->assertFalse(ExclusiveDistrictSeat::holdsSeat('APPLIED'));
        $this->assertFalse(ExclusiveDistrictSeat::holdsSeat('APPROVED'));
        $this->assertFalse(ExclusiveDistrictSeat::holdsSeat('SUSPENDED'));
        $this->assertSame('10', ExclusiveDistrictSeat::key(10));
        $this->assertTrue(ExclusiveDistrictSeat::canTransition('APPROVED', 'ACTIVE'));
        $this->assertFalse(ExclusiveDistrictSeat::canTransition('APPLIED', 'ACTIVE'));
    }
}
