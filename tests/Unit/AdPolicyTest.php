<?php

namespace Tests\Unit;

use App\Platform\AdPolicy;
use Tests\TestCase;

class AdPolicyTest extends TestCase
{
    public function test_safe_placements_are_home_travel_discovery_catalog(): void
    {
        $this->assertTrue(AdPolicy::canServe('home')['ok']);
        $this->assertTrue(AdPolicy::canServe('travel')['ok']);
        $this->assertTrue(AdPolicy::canServe('discovery')['ok']);
        $this->assertTrue(AdPolicy::canServe('catalog')['ok']);
    }

    public function test_critical_screens_never_serve_ads(): void
    {
        foreach (['trip', 'sos', 'payment', 'otp', 'navigation', 'driver_navigation', 'active_booking', 'booking'] as $placement) {
            $this->assertSame('blocked_placement', AdPolicy::canServe($placement)['reason']);
        }
        $this->assertFalse(AdPolicy::canServe('home', 'active_trip')['ok']);
    }

    public function test_state_district_and_city_targeting(): void
    {
        $this->assertTrue(AdPolicy::matchesLocation(1, 10, 'Patna', 1, 10, 'Patna Junction, Patna'));
        $this->assertFalse(AdPolicy::matchesLocation(1, 10, null, 2, 10, null));
        $this->assertFalse(AdPolicy::matchesLocation(1, 10, 'Patna', 1, 20, 'Patna'));
        $this->assertContains('other', array_keys(AdPolicy::CATEGORIES));
    }
}
