<?php

namespace Tests\Unit;

use App\Platform\PaymentLifecycle;
use Tests\TestCase;

class PaymentLifecycleTest extends TestCase
{
    public function test_normalizes_supported_methods(): void
    {
        $this->assertSame('cash', PaymentLifecycle::normalizeMethod('COD'));
        $this->assertSame('upi', PaymentLifecycle::normalizeMethod('PhonePe'));
        $this->assertSame('card', PaymentLifecycle::normalizeMethod('debit'));
        $this->assertSame('wallet', PaymentLifecycle::normalizeMethod('WALLET'));
        $this->assertSame('advance', PaymentLifecycle::normalizeMethod('advance'));
        $this->assertSame('partial', PaymentLifecycle::normalizeMethod('partial'));
    }

    public function test_webhook_hmac_is_required(): void
    {
        $canonical = PaymentLifecycle::webhookCanonical('payment.captured', 'KCPTEST', 21600);
        $signature = PaymentLifecycle::signWebhook('karnacab-dev-pay-hook', $canonical);
        $this->assertTrue(PaymentLifecycle::verifyWebhook('karnacab-dev-pay-hook', $canonical, $signature));
        $this->assertFalse(PaymentLifecycle::verifyWebhook('karnacab-dev-pay-hook', $canonical, 'deadbeef'));
        $this->assertFalse(PaymentLifecycle::verifyWebhook('karnacab-dev-pay-hook', $canonical, null));
    }
}
