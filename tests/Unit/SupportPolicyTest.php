<?php

namespace Tests\Unit;

use App\Platform\SupportPolicy;
use Tests\TestCase;

class SupportPolicyTest extends TestCase
{
    public function test_status_flow_is_strict(): void
    {
        $this->assertSame(
            ['open', 'assigned', 'in_progress', 'resolved', 'closed'],
            SupportPolicy::STATUSES,
        );
        $this->assertTrue(SupportPolicy::canAdvance('open', 'assigned'));
        $this->assertTrue(SupportPolicy::canAdvance('assigned', 'in_progress'));
        $this->assertTrue(SupportPolicy::canAdvance('in_progress', 'resolved'));
        $this->assertTrue(SupportPolicy::canAdvance('resolved', 'closed'));
        $this->assertFalse(SupportPolicy::canAdvance('open', 'resolved'));
        $this->assertFalse(SupportPolicy::canAdvance('open', 'in_progress'));
        $this->assertFalse(SupportPolicy::canAdvance('closed', 'open'));
        $this->assertSame('in_progress', SupportPolicy::normalize('waiting'));
        $this->assertSame('in_progress', SupportPolicy::normalize('pending'));
    }
}
