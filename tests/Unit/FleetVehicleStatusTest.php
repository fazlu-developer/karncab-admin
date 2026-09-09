<?php

namespace Tests\Unit;

use App\Platform\FleetVehicleStatus;
use PHPUnit\Framework\TestCase;

class FleetVehicleStatusTest extends TestCase
{
    public function test_locked_statuses_override_live_activity(): void
    {
        $resolved = FleetVehicleStatus::resolve([
            'stored' => 'maintenance',
            'assignedDriverId' => 1,
            'driverOnline' => true,
            'hasActiveTrip' => true,
        ]);
        $this->assertSame('maintenance', $resolved['status']);
        $this->assertTrue($resolved['locked']);
    }

    public function test_live_trip_resolves_to_on_trip(): void
    {
        $resolved = FleetVehicleStatus::resolve([
            'stored' => 'available',
            'assignedDriverId' => 1,
            'driverOnline' => true,
            'hasActiveTrip' => true,
        ]);
        $this->assertSame('on_trip', $resolved['status']);
        $this->assertSame('On Trip', $resolved['label']);
    }
}
