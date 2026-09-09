<?php

namespace Tests\Unit;

use App\Platform\LiveFix;
use PHPUnit\Framework\TestCase;

class LiveFixTest extends TestCase
{
    public function test_near_duplicate_pings_are_skipped(): void
    {
        $prev = LiveFix::make(1, 2, null, 10, 25.61, 85.14, null, null, 'online', 1_000_000);
        $this->assertTrue(LiveFix::shouldSkipPing($prev, 25.61, 85.14, 1_000_500));
        $this->assertFalse(LiveFix::shouldSkipPing($prev, 25.62, 85.15, 1_000_500));
    }

    public function test_persist_is_throttled_by_time_and_distance(): void
    {
        $prev = LiveFix::make(1, 2, null, 10, 25.61, 85.14, null, null, 'online', 1_000_000);
        $this->assertFalse(LiveFix::shouldPersist($prev, 25.61001, 85.14001, 1_005_000));
        $this->assertTrue(LiveFix::shouldPersist($prev, 25.61, 85.14, 1_020_000));
        $this->assertTrue(LiveFix::shouldPersist(null, 25.61, 85.14, 1_000_000));
    }
}
