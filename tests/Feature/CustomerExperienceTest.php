<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class CustomerExperienceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedCustomer();
    }

    public function test_customer_login_opens_customer_dashboard(): void
    {
        $rider = User::factory()->create([
            'email' => 'rider-me@karnacab.local',
            'password' => 'ChangeMe@123',
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 800,
        ]);

        $this->post('/login', [
            'email' => 'rider-me@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->assertRedirect('/me');

        $this->actingAs($rider)
            ->get('/me')
            ->assertOk()
            ->assertSee('Booking history')
            ->assertSee('Upcoming bookings')
            ->assertSee('Scheduled rides')
            ->assertSee('Active ride')
            ->assertSee('Parcel history')
            ->assertSee('Travel bookings')
            ->assertSee('Bulk bookings')
            ->assertSee('Corporate bookings')
            ->assertSee('Wallet')
            ->assertSee('Coupons')
            ->assertSee('Offers')
            ->assertSee('Notifications')
            ->assertSee('Ratings')
            ->assertSee('Complaints')
            ->assertSee('Invoices')
            ->assertSee('Saved locations')
            ->assertSee('Emergency contact')
            ->assertSee('Family booking')
            ->assertSee('Booking for another person')
            ->assertSee('KC-MINE')
            ->assertSee('KC-GUEST')
            ->assertDontSee('KC-OTHER');

        $this->actingAs($rider)->get('/dashboard')->assertRedirect(route('customer.dashboard'));
    }

    public function test_dashboard_api_is_isolated_and_fleet_is_forbidden(): void
    {
        $rider = $this->rider();
        $payload = $this->actingAs($rider)->getJson('/api/v1/me')->assertOk()->json();
        $this->assertContains('profile', $payload['catalog']['tools']);
        $historyRefs = array_column($payload['history'], 'publicRef');
        $this->assertContains('KC-MINE', $historyRefs);
        $this->assertNotContains('KC-OTHER', $historyRefs);
        $this->assertSame('KC-CORP', $payload['corporate'][0]['publicRef']);
        $this->assertSame('KC-GUEST', $payload['guest'][0]['publicRef']);
        $this->assertSame('KC-SKED', $payload['scheduled'][0]['publicRef']);
        $this->assertSame('Home', $payload['places'][0]['title']);
        $this->assertSame('Aisha', $payload['family'][0]['name']);
        $this->assertNotNull($payload['activeRide']);
        $this->assertSame('ONGOING', $payload['activeRide']['status']);

        $other = User::factory()->create(['role' => OperatorRole::CUSTOMER, 'nest_user_id' => 801]);
        $otherPayload = $this->actingAs($other)->getJson('/api/v1/me')->assertOk()->json();
        $this->assertSame(['KC-OTHER'], array_column($otherPayload['history'], 'publicRef'));
        $this->assertEmpty($otherPayload['places']);
        $this->assertEmpty($otherPayload['family']);

        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/me')->assertForbidden();
        $this->actingAs($fleet)->getJson('/api/v1/me')->assertForbidden();
    }

    public function test_customer_can_update_profile_family_place_and_complaint(): void
    {
        $rider = $this->rider();
        $this->actingAs($rider)->patchJson('/api/v1/me/profile', [
            'name' => 'Patna Rider',
            'phone' => '9000000800',
        ])->assertOk()->assertJsonPath('name', 'Patna Rider');

        $this->actingAs($rider)->patchJson('/api/v1/me/emergency', [
            'emergency_name' => 'Home',
            'emergency_phone' => '9111111111',
        ])->assertOk()->assertJsonPath('phone', '9111111111');

        $this->actingAs($rider)->postJson('/api/v1/me/family', [
            'name' => 'Imran',
            'phone' => '9876543210',
            'relation' => 'Brother',
        ])->assertCreated()->assertJsonPath('name', 'Imran');

        $this->actingAs($rider)->postJson('/api/v1/me/places', [
            'title' => 'Office',
            'address' => 'Boring Road',
        ])->assertCreated();

        $this->actingAs($rider)->postJson('/api/v1/me/complaints', [
            'subject' => 'Driver was late',
        ])->assertCreated();

        $this->actingAs($rider)->postJson('/api/v1/me/ratings', [
            'booking_id' => 2,
            'stars' => 5,
            'comment' => 'Smooth ride',
        ])->assertCreated()->assertJsonPath('stars', 5);

        $other = User::factory()->create(['role' => OperatorRole::CUSTOMER, 'nest_user_id' => 801]);
        $this->actingAs($other)->deleteJson('/api/v1/me/family/1')->assertNotFound();
    }

    private function rider(): User
    {
        return User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 800,
        ]);
    }

    private function seedCustomer(): void
    {
        $db = DB::connection('platform');
        $db->table('users')->insert([
            [
                'id' => 800,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'name' => 'Rider',
                'email' => 'me@karnacab.local',
                'password_hash' => 'x',
                'emergency_name' => 'Home',
                'emergency_phone' => '9000000000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 801,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'name' => 'Other',
                'email' => 'other-me@karnacab.local',
                'password_hash' => 'x',
                'emergency_name' => null,
                'emergency_phone' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $db->table('bookings')->insert([
            [
                'id' => 1,
                'public_ref' => 'KC-LIVE',
                'customer_id' => 800,
                'status' => 'ONGOING',
                'product' => 'LOCAL_CAB',
                'corporate_account_id' => null,
                'booked_for_other' => false,
                'passenger_name' => null,
                'passenger_phone' => null,
                'scheduled_at' => null,
                'pickup_text' => 'Patna Jn',
                'drop_text' => 'Gandhi Maidan',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'public_ref' => 'KC-MINE',
                'customer_id' => 800,
                'status' => 'COMPLETED',
                'product' => 'LOCAL_CAB',
                'corporate_account_id' => null,
                'booked_for_other' => false,
                'passenger_name' => null,
                'passenger_phone' => null,
                'scheduled_at' => null,
                'pickup_text' => 'A',
                'drop_text' => 'B',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'public_ref' => 'KC-OTHER',
                'customer_id' => 801,
                'status' => 'COMPLETED',
                'product' => 'LOCAL_CAB',
                'corporate_account_id' => null,
                'booked_for_other' => false,
                'passenger_name' => null,
                'passenger_phone' => null,
                'scheduled_at' => null,
                'pickup_text' => 'X',
                'drop_text' => 'Y',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'public_ref' => 'KC-CORP',
                'customer_id' => 800,
                'status' => 'COMPLETED',
                'product' => 'LOCAL_CAB',
                'corporate_account_id' => 1,
                'booked_for_other' => false,
                'passenger_name' => null,
                'passenger_phone' => null,
                'scheduled_at' => null,
                'pickup_text' => 'Office',
                'drop_text' => 'Airport',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'public_ref' => 'KC-GUEST',
                'customer_id' => 800,
                'status' => 'ASSIGNED',
                'product' => 'LOCAL_CAB',
                'corporate_account_id' => null,
                'booked_for_other' => true,
                'passenger_name' => 'Imran',
                'passenger_phone' => '9876543210',
                'scheduled_at' => null,
                'pickup_text' => 'Home',
                'drop_text' => 'School',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 6,
                'public_ref' => 'KC-SKED',
                'customer_id' => 800,
                'status' => 'CONFIRMED',
                'product' => 'SCHEDULE',
                'corporate_account_id' => null,
                'booked_for_other' => false,
                'passenger_name' => null,
                'passenger_phone' => null,
                'scheduled_at' => now()->addDay(),
                'pickup_text' => 'Airport',
                'drop_text' => 'Hotel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        $db->table('parcel_shipments')->insert([
            'public_ref' => 'PK-1', 'customer_id' => 800, 'status' => 'delivered', 'pickup_text' => 'A', 'drop_text' => 'B',
        ]);
        $db->table('travel_bookings')->insert([
            'public_ref' => 'TV-1', 'customer_id' => 800, 'status' => 'confirmed',
        ]);
        $db->table('bulk_bookings')->insert([
            'public_ref' => 'BK-1', 'customer_id' => 800, 'status' => 'quoted', 'event_key' => 'wedding',
        ]);
        $db->table('wallets')->insert([
            'owner_type' => 'CUSTOMER', 'owner_user_id' => 800, 'balance_paise' => 25000,
        ]);
        $db->table('invoices')->insert([
            'public_ref' => 'KCI1', 'customer_id' => 800, 'status' => 'paid', 'total_paise' => 20000, 'subtotal_paise' => 20000,
        ]);
        $db->table('user_notifications')->insert([
            'user_id' => 800, 'title' => 'Driver assigned', 'body' => 'Your cab is on the way', 'kind' => 'ride', 'created_at' => now(),
        ]);
        $db->table('user_places')->insert([
            'user_id' => 800, 'kind' => 'SAVED', 'title' => 'Home', 'address' => 'Patna Junction', 'lat' => 25.6, 'lng' => 85.1, 'created_at' => now(),
        ]);
        $db->table('family_members')->insert([
            'user_id' => 800, 'name' => 'Aisha', 'phone' => '9876543210', 'relation' => 'Sister', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('support_tickets')->insert([
            'user_id' => 800, 'public_ref' => 'CMP1', 'kind' => 'complaint', 'status' => 'open', 'subject' => 'AC not working',
        ]);
    }
}
