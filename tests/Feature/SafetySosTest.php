<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class SafetySosTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedSafety();
    }

    public function test_customer_and_driver_can_trigger_sos_and_store_required_fields(): void
    {
        $rider = $this->rider();
        $payload = $this->actingAs($rider)->postJson('/api/v1/safety/sos', [
            'kind' => 'emergency',
            'booking_id' => 1,
            'lat' => 25.61,
            'lng' => 85.14,
        ])->assertCreated()->json();

        $this->assertSame('Home', $payload['emergency']['name']);
        $this->assertSame('9876543210', $payload['emergency']['phone']);
        $this->assertSame('KC-LIVE', $payload['trip']['publicRef']);
        $this->assertNotNull($payload['incident']['timestamp']);
        $this->assertSame(25.61, $payload['incident']['location']['lat']);
        $this->assertSame('open', $payload['incident']['status']);
        $this->assertSame('Home', $payload['incident']['emergencyContact']['name']);

        $row = DB::connection('platform')->table('safety_incidents')->where('id', $payload['incident']['id'])->first();
        $this->assertSame(800, (int) $row->reporter_user_id);
        $this->assertSame(1, (int) $row->booking_id);
        $this->assertSame('9876543210', $row->emergency_phone);
        $this->assertSame('CUSTOMER', $row->actor_role);

        $driver = $this->driverUser();
        $this->actingAs($driver)->postJson('/api/v1/safety/sos', [
            'kind' => 'police',
            'booking_id' => 1,
            'lat' => 25.62,
            'lng' => 85.15,
        ])->assertCreated();
    }

    public function test_emergency_contacts_trip_share_and_driver_verification(): void
    {
        $rider = $this->rider();
        $this->actingAs($rider)->postJson('/api/v1/safety/emergency-contact', [
            'name' => 'Amina',
            'phone' => '9123456789',
            'relation' => 'Sister',
        ])->assertOk()->assertJsonPath('emergency.phone', '9123456789');

        $share = $this->actingAs($rider)->postJson('/api/v1/safety/share', ['booking_id' => 1])->assertOk()->json();
        $this->assertStringContainsString('/safety/share/', $share['url']);
        $this->get($share['url'])->assertOk()->assertSee('Rakesh')->assertDontSee('4321')->assertSee('****1234');
        $this->getJson('/api/v1/safety/share/'.$share['token'])
            ->assertOk()
            ->assertJsonPath('otp', null)
            ->assertJsonPath('vehicle.plateHint', '****1234');

        $verify = $this->actingAs($rider)->getJson('/api/v1/safety/bookings/1/verify')->assertOk()->json();
        $this->assertSame('Imran Driver', $verify['driver']['name']);
        $this->assertSame('BR01AB1234', $verify['vehicle']['number']);
        $this->assertSame('SEDAN', $verify['vehicle']['type']);
        $this->assertSame(4.8, $verify['driver']['rating']);
        $this->assertSame('verified', $verify['driver']['verificationStatus']);
        $this->assertSame('4321', $verify['otp']['start']);
        $this->assertSame('8765', $verify['otp']['end']);

        $asDriver = $this->actingAs($this->driverUser())->getJson('/api/v1/safety/bookings/1/verify')->assertOk()->json();
        $this->assertNull($asDriver['otp']['start']);
        $this->assertNull($asDriver['otp']['end']);
    }

    public function test_start_and_end_otp_are_required_and_isolated(): void
    {
        $driver = $this->driverUser();
        $this->actingAs($driver)->postJson('/api/v1/safety/bookings/2/otp', [
            'action' => 'start',
            'otp' => '0000',
        ])->assertForbidden();

        $this->actingAs($driver)->postJson('/api/v1/safety/bookings/2/otp', [
            'action' => 'start',
            'otp' => '1111',
        ])->assertOk()->assertJsonPath('status', 'STARTED')->assertJsonPath('otp.start', null);

        $this->actingAs($driver)->postJson('/api/v1/safety/bookings/2/otp', [
            'action' => 'complete',
            'otp' => '2222',
        ])->assertOk()->assertJsonPath('status', 'COMPLETED');

        $this->actingAs($this->rider())->postJson('/api/v1/safety/bookings/2/otp', [
            'action' => 'start',
            'otp' => '1111',
        ])->assertForbidden();
    }

    public function test_ops_workspace_is_territory_scoped_and_fleet_is_forbidden(): void
    {
        $this->actingAs($this->rider())->postJson('/api/v1/safety/sos', [
            'kind' => 'karnacab', 'booking_id' => 1, 'lat' => 25.6, 'lng' => 85.1,
        ])->assertCreated();

        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->get('/safety')->assertOk()->assertSee('Safety, support');
        $this->actingAs($admin)->get('/ops/safety')->assertRedirect(route('safety.index'));
        $this->actingAs($admin)->getJson('/api/v1/safety/incidents')->assertOk()
            ->assertJsonFragment(['bookingRef' => 'KC-LIVE']);

        $head = User::factory()->create(['role' => OperatorRole::DISTRICT_HEAD, 'district_id' => 20, 'state_id' => 2]);
        $this->actingAs($head)->getJson('/api/v1/safety/incidents')->assertOk()
            ->assertJsonPath('incidents', []);

        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/safety')->assertForbidden();
        $this->actingAs($fleet)->postJson('/api/v1/safety/sos', ['kind' => 'emergency'])->assertForbidden();
    }

    private function rider(): User
    {
        return User::factory()->create(['role' => OperatorRole::CUSTOMER, 'nest_user_id' => 800]);
    }

    private function driverUser(): User
    {
        return User::factory()->create(['role' => OperatorRole::DRIVER, 'nest_user_id' => 900, 'district_id' => 10]);
    }

    private function seedSafety(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar'], ['id' => 2, 'name' => 'Jharkhand']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna'], ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi']]);
        $db->table('users')->insert([
            [
                'id' => 800, 'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Rakesh Rider',
                'email' => 'safe-rider@karnacab.local', 'password_hash' => 'x',
                'emergency_name' => 'Home', 'emergency_phone' => '9876543210',
                'district_id' => 10, 'last_lat' => 25.6, 'last_lng' => 85.1,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 900, 'role' => 'DRIVER', 'status' => 'ACTIVE', 'name' => 'Imran Driver',
                'email' => 'safe-driver@karnacab.local', 'password_hash' => 'x',
                'emergency_name' => null, 'emergency_phone' => null,
                'district_id' => 10, 'last_lat' => null, 'last_lng' => null,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $db->table('drivers')->insert([
            'id' => 1, 'user_id' => 900, 'online' => true, 'license_no' => 'BR-9',
            'kyc_status' => 'verified', 'rating_avg' => 4.8,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('vehicles')->insert([
            'id' => 1, 'registration_no' => 'BR01AB1234', 'category' => 'SEDAN', 'color' => 'White',
            'driver_id' => 1, 'district_id' => 10, 'status' => 'on_trip',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $now = ['created_at' => now(), 'updated_at' => now()];
        $db->table('bookings')->insert([
            [
                'id' => 1, 'public_ref' => 'KC-LIVE', 'customer_id' => 800, 'driver_id' => 1, 'vehicle_id' => 1,
                'district_id' => 10, 'status' => 'ONGOING', 'product' => 'LOCAL_CAB',
                'pickup_text' => 'Patna Jn', 'drop_text' => 'Gandhi Maidan',
                'start_otp' => '4321', 'end_otp' => '8765',
                'pickup_lat' => 25.6, 'pickup_lng' => 85.1,
            ] + $now,
            [
                'id' => 2, 'public_ref' => 'KC-PIN', 'customer_id' => 800, 'driver_id' => 1, 'vehicle_id' => 1,
                'district_id' => 10, 'status' => 'DRIVER_ARRIVED', 'product' => 'LOCAL_CAB',
                'pickup_text' => 'A', 'drop_text' => 'B',
                'start_otp' => '1111', 'end_otp' => '2222',
                'pickup_lat' => null, 'pickup_lng' => null,
            ] + $now,
        ]);
        $db->table('system_settings')->insert([
            ['key' => 'driver_safety_sos', 'value' => '112'],
            ['key' => 'driver_safety_helpline', 'value' => '08041234500'],
        ]);
    }
}
