<?php

namespace Tests\Unit;

use App\Platform\SafetyPolicy;
use Tests\TestCase;

class SafetyPolicyTest extends TestCase
{
    public function test_share_helpers_mask_identity(): void
    {
        $this->assertSame('Rakesh', SafetyPolicy::firstName('Rakesh Kumar'));
        $this->assertSame('****1234', SafetyPolicy::plateHint('BR01AB1234'));
        $this->assertSame('9999', SafetyPolicy::phoneLast4('+91 9000009999'));
        $this->assertSame('9876543210', SafetyPolicy::indianMobile('9876543210'));
        $this->assertNull(SafetyPolicy::indianMobile('12345'));
    }

    public function test_pin_must_match(): void
    {
        SafetyPolicy::assertPin('start', '1234', '1234');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        SafetyPolicy::assertPin('end', '9999', '0000');
    }
}
