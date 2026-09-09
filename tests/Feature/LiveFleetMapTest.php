<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class LiveFleetMapTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedMap();
    }

    public function test_super_admin_sees_all_vehicles_and_district_cannot_widen_scope(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $district = User::factory()->create([
            'role' => OperatorRole::DISTRICT_HEAD,
            'district_id' => 10,
            'state_id' => 1,
        ]);
        $state = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);
        $fleet = User::factory()->create([
            'role' => OperatorRole::FLEET_OWNER,
            'fleet_owner_id' => 1,
        ]);

        $this->actingAs($admin)
            ->getJson('/api/v1/live/map')
            ->assertOk()
            ->assertJsonFragment(['vehicleNumber' => 'BR01LIVE1'])
            ->assertJsonFragment(['vehicleNumber' => 'JH01LIVE2']);

        $this->actingAs($state)
            ->getJson('/api/v1/live/map')
            ->assertOk()
            ->assertJsonFragment(['vehicleNumber' => 'BR01LIVE1'])
            ->assertJsonMissing(['vehicleNumber' => 'JH01LIVE2']);

        $this->actingAs($district)
            ->getJson('/api/v1/live/map?districtId=20')
            ->assertOk()
            ->assertJsonFragment(['vehicleNumber' => 'BR01LIVE1'])
            ->assertJsonMissing(['vehicleNumber' => 'JH01LIVE2']);

        $this->actingAs($fleet)
            ->getJson('/api/v1/live/map')
            ->assertOk()
            ->assertJsonFragment(['vehicleNumber' => 'BR01LIVE1'])
            ->assertJsonMissing(['vehicleNumber' => 'JH01LIVE2'])
            ->assertJsonPath('vehicles.0.currentBooking.publicRef', 'KCLIVE');
    }

    public function test_driver_ingest_does_not_write_gps_onto_the_booking(): void
    {
        $driverUser = User::factory()->create([
            'role' => OperatorRole::DRIVER,
            'nest_user_id' => 501,
            'email' => 'map-driver@karnacab.local',
        ]);
        $pickupBefore = DB::connection('platform')->table('bookings')->where('public_ref', 'KCLIVE')->value('pickup_text');

        $first = $this->actingAs($driverUser)
            ->postJson('/api/v1/live/fix', [
                'lat' => 25.61234,
                'lng' => 85.14111,
                'heading' => 90,
                'speed' => 8.5,
            ])
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('persisted', true)
            ->json();

        $this->assertSame($pickupBefore, DB::connection('platform')->table('bookings')->where('public_ref', 'KCLIVE')->value('pickup_text'));
        $this->assertEqualsWithDelta(25.61234, (float) DB::connection('platform')->table('users')->where('id', 501)->value('last_lat'), 0.0001);
        $this->assertNotNull(DB::connection('platform')->table('vehicles')->where('id', 1)->value('last_fix_at'));

        $this->actingAs($driverUser)
            ->postJson('/api/v1/live/fix', [
                'lat' => 25.61235,
                'lng' => 85.14112,
            ])
            ->assertOk()
            ->assertJsonPath('persisted', false);

        $this->actingAs($driverUser)
            ->getJson('/api/v1/live/me')
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('bookingId', $first['bookingId']);
    }

    public function test_customer_sees_assigned_driver_only_on_active_booking(): void
    {
        $customer = User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 401,
        ]);
        $stranger = User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 402,
        ]);
        $driverUser = User::factory()->create([
            'role' => OperatorRole::DRIVER,
            'nest_user_id' => 501,
            'email' => 'map-driver@karnacab.local',
        ]);
        $this->actingAs($driverUser)->postJson('/api/v1/live/fix', ['lat' => 25.6, 'lng' => 85.1])->assertOk();

        $this->actingAs($customer)
            ->getJson('/api/v1/live/bookings/1')
            ->assertOk()
            ->assertJsonPath('visible', true)
            ->assertJsonPath('booking.publicRef', 'KCLIVE');

        $this->actingAs($stranger)
            ->getJson('/api/v1/live/bookings/1')
            ->assertOk()
            ->assertJsonPath('visible', false);

        DB::connection('platform')->table('bookings')->where('id', 1)->update(['status' => 'COMPLETED']);
        $this->actingAs($customer)
            ->getJson('/api/v1/live/bookings/1')
            ->assertOk()
            ->assertJsonPath('visible', false);

        $this->actingAs($customer)->getJson('/api/v1/live/map')->assertForbidden();
    }

    public function test_advertiser_cannot_open_live_map(): void
    {
        $ads = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($ads)->getJson('/api/v1/live/map')->assertForbidden();
        $this->actingAs($ads)->get('/live-map')->assertForbidden();
    }

    public function test_live_map_page_renders_for_admin(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)
            ->get('/live-map')
            ->assertOk()
            ->assertSee('Live fleet map')
            ->assertSee('BR01LIVE1')
            ->assertSee('Ravi Driver');
        $this->actingAs($admin)->get('/ops/map')->assertRedirect('/live-map');
    }

    private function seedMap(): void
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
        $db->table('users')->insert([
            [
                'id' => 401,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'name' => 'Patna Rider',
                'email' => 'live-cust@karnacab.local',
                'password_hash' => 'x',
                'district_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 402,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'name' => 'Other Rider',
                'email' => 'other-cust@karnacab.local',
                'password_hash' => 'x',
                'district_id' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 501,
                'role' => 'DRIVER',
                'status' => 'ACTIVE',
                'name' => 'Ravi Driver',
                'email' => 'map-driver@karnacab.local',
                'password_hash' => 'x',
                'district_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 502,
                'role' => 'DRIVER',
                'status' => 'ACTIVE',
                'name' => 'Ranchi Driver',
                'email' => 'ranchi-driver@karnacab.local',
                'password_hash' => 'x',
                'district_id' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $db->table('fleet_owners')->insert([
            ['id' => 1, 'user_id' => 501, 'trade_name' => 'Patna Live Fleet'],
            ['id' => 2, 'user_id' => 502, 'trade_name' => 'Ranchi Live Fleet'],
        ]);
        $db->table('drivers')->insert([
            ['id' => 11, 'user_id' => 501, 'fleet_owner_id' => 1, 'online' => true, 'duty_status' => 'online', 'license_no' => 'BR-L', 'kyc_status' => 'verified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 12, 'user_id' => 502, 'fleet_owner_id' => 2, 'online' => false, 'duty_status' => 'offline', 'license_no' => 'JH-L', 'kyc_status' => 'verified', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('vehicles')->insert([
            [
                'id' => 1,
                'registration_no' => 'BR01LIVE1',
                'status' => 'available',
                'category' => 'SEDAN',
                'district_id' => 10,
                'fleet_owner_id' => 1,
                'driver_id' => 11,
                'last_lat' => 25.61,
                'last_lng' => 85.14,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'registration_no' => 'JH01LIVE2',
                'status' => 'offline',
                'category' => 'SUV',
                'district_id' => 20,
                'fleet_owner_id' => 2,
                'driver_id' => 12,
                'last_lat' => 23.34,
                'last_lng' => 85.31,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $db->table('bookings')->insert([
            'id' => 1,
            'public_ref' => 'KCLIVE',
            'customer_id' => 401,
            'driver_id' => 11,
            'vehicle_id' => 1,
            'product' => 'ONE_WAY',
            'status' => 'ONGOING',
            'pickup_text' => 'Patna Junction',
            'drop_text' => 'Airport',
            'district_id' => 10,
            'quote_paise' => 25000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
