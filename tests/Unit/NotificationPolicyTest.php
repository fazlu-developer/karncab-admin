<?php

namespace Tests\Unit;

use App\Platform\NotificationPolicy;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    public function test_catalog_lists_channels_and_required_events(): void
    {
        $this->assertSame(['in_app', 'push', 'sms', 'email'], NotificationPolicy::CHANNELS);
        foreach ([
            'otp', 'booking_confirmation', 'driver_assigned', 'driver_arriving', 'ride_started',
            'ride_completed', 'payment', 'refund', 'cancellation', 'parcel_update', 'document_expiry',
            'kyc_approval', 'kyc_rejection', 'wallet_transaction', 'franchise_approval', 'coupon', 'support_update',
        ] as $event) {
            $this->assertArrayHasKey($event, NotificationPolicy::EVENTS);
        }
        $otp = NotificationPolicy::render('otp', ['code' => '4321']);
        $this->assertSame(['sms', 'email'], $otp['channels']);
        $this->assertStringContainsString('4321', $otp['body']);
        $this->assertSame(
            'Your KarnaCab booking KC-1 is confirmed.',
            NotificationPolicy::render('booking_confirmation', ['ref' => 'KC-1'])['body'],
        );
    }
}
