<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
    }

    public function test_granular_permissions_differ_by_role(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $district = User::factory()->create(['role' => OperatorRole::DISTRICT_HEAD, 'district_id' => 10]);
        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $advertiser = User::factory()->create(['role' => OperatorRole::ADVERTISER]);

        $this->assertTrue($admin->can('users.delete'));
        $this->assertTrue($admin->can('fare.manage'));
        $this->assertTrue($admin->can('users.write'));
        $this->assertTrue($district->can('users.view'));
        $this->assertTrue($district->can('bookings.view'));
        $this->assertTrue($district->can('reports.export'));
        $this->assertTrue($district->can('franchise.manage'));
        $this->assertFalse($district->can('users.delete'));
        $this->assertFalse($district->can('fare.manage'));
        $this->assertFalse($district->can('advertising.write'));
        $this->assertTrue($fleet->can('fleet.manage'));
        $this->assertTrue($fleet->can('drivers.view'));
        $this->assertFalse($fleet->can('users.view'));
        $this->assertFalse($fleet->can('drivers.approve'));
        $this->assertTrue($advertiser->can('advertising.edit'));
        $this->assertFalse($advertiser->can('bookings.view'));
    }

    public function test_district_head_cannot_see_or_export_another_district_booking(): void
    {
        $this->seedTerritory();

        $head = User::factory()->create([
            'role' => OperatorRole::DISTRICT_HEAD,
            'district_id' => 10,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->get('/api/v1/ops/bookings?districtId=20')
            ->assertOk()
            ->assertJsonMissing(['publicRef' => 'KCOTHER'])
            ->assertJsonFragment(['publicRef' => 'KCMINE']);

        $csv = $this->actingAs($head)
            ->get('/api/v1/ops/bookings/export?districtId=20')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('KCMINE', $csv);
        $this->assertStringNotContainsString('KCOTHER', $csv);

        $this->actingAs($head)
            ->get('/users')
            ->assertOk()
            ->assertSee('Patna Rider')
            ->assertDontSee('Gaya Rider');
    }

    public function test_state_head_is_limited_to_assigned_state(): void
    {
        $this->seedTerritory();

        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->getJson('/api/v1/ops/bookings?stateId=2')
            ->assertOk()
            ->assertJsonFragment(['publicRef' => 'KCMINE'])
            ->assertJsonMissing(['publicRef' => 'KCOTHER']);
    }

    public function test_fleet_owner_only_sees_own_fleet_drivers(): void
    {
        $this->seedTerritory();

        $owner = User::factory()->create([
            'role' => OperatorRole::FLEET_OWNER,
            'fleet_owner_id' => 1,
        ]);

        $this->actingAs($owner)
            ->get('/drivers')
            ->assertOk()
            ->assertSee('Fleet One Driver')
            ->assertDontSee('Other Fleet Driver');
    }

    public function test_advertiser_cannot_open_bookings_module(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::ADVERTISER]);

        $this->actingAs($user)
            ->get('/ops/bookings')
            ->assertForbidden();
    }

    private function seedTerritory(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([
            ['id' => 1, 'name' => 'Bihar'],
            ['id' => 2, 'name' => 'Jharkhand'],
        ]);
        $db->table('districts')->insert([
            ['id' => 10, 'state_id' => 1, 'name' => 'Patna'],
            ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi'],
        ]);
        $mine = $db->table('users')->insertGetId([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Patna Rider',
            'email' => 'patna@karnacab.local',
            'phone' => '9000000010',
            'password_hash' => 'x',
            'state_id' => 1,
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $other = $db->table('users')->insertGetId([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Gaya Rider',
            'email' => 'ranchi@karnacab.local',
            'phone' => '9000000020',
            'password_hash' => 'x',
            'state_id' => 2,
            'district_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $fleetUser = $db->table('users')->insertGetId([
            'role' => 'FLEET_OWNER',
            'status' => 'ACTIVE',
            'name' => 'Fleet One',
            'email' => 'fleet1@karnacab.local',
            'phone' => '9000000030',
            'password_hash' => 'x',
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('fleet_owners')->insert([
            'id' => 1,
            'user_id' => $fleetUser,
            'trade_name' => 'Patna Fleet',
        ]);
        $driverUser = $db->table('users')->insertGetId([
            'role' => 'DRIVER',
            'status' => 'ACTIVE',
            'name' => 'Fleet One Driver',
            'email' => 'd1@karnacab.local',
            'phone' => '9000000040',
            'password_hash' => 'x',
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherDriverUser = $db->table('users')->insertGetId([
            'role' => 'DRIVER',
            'status' => 'ACTIVE',
            'name' => 'Other Fleet Driver',
            'email' => 'd2@karnacab.local',
            'phone' => '9000000050',
            'password_hash' => 'x',
            'district_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('drivers')->insert([
            [
                'user_id' => $driverUser,
                'fleet_owner_id' => 1,
                'online' => false,
                'license_no' => 'BR-1',
                'kyc_status' => 'verified',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $otherDriverUser,
                'fleet_owner_id' => 99,
                'online' => false,
                'license_no' => 'JH-1',
                'kyc_status' => 'verified',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $db->table('bookings')->insert([
            [
                'public_ref' => 'KCMINE',
                'customer_id' => $mine,
                'product' => 'ONE_WAY',
                'status' => 'COMPLETED',
                'pickup_text' => 'Patna',
                'drop_text' => 'Gaya',
                'district_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'public_ref' => 'KCOTHER',
                'customer_id' => $other,
                'product' => 'ONE_WAY',
                'status' => 'COMPLETED',
                'pickup_text' => 'Ranchi',
                'drop_text' => 'Bokaro',
                'district_id' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
