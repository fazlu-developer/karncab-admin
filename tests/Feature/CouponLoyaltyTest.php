<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class CouponLoyaltyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedCoupons();
    }

    public function test_admin_opens_coupon_workspace(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->get('/coupons')->assertOk()->assertSee('Coupons, offers');
        $this->actingAs($admin)->get('/ops/coupons')->assertRedirect(route('coupons.index'));
        $this->actingAs($admin)->getJson('/api/v1/coupons/catalog')
            ->assertOk()
            ->assertJsonPath('note', 'Coupons are quoted on the server. Discount amounts from the app are ignored.');
    }

    public function test_admin_can_configure_percent_fixed_limits_and_targeting(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $created = $this->actingAs($admin)->postJson('/api/v1/coupons', [
            'code' => 'AIRMAX',
            'title' => 'Airport cap',
            'kind' => 'percent',
            'percent' => 25,
            'max_discount_paise' => 2000,
            'min_fare_paise' => 10_000,
            'product' => 'AIRPORT',
            'state_id' => 1,
            'district_id' => 10,
            'audience' => 'first_ride',
            'usage_limit' => 100,
            'user_limit' => 1,
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'active' => true,
        ])->assertCreated()->json();

        $this->assertSame('AIRMAX', $created['code']);
        $this->assertSame(2000, $created['maxDiscountPaise']);
        $this->assertSame('AIRPORT', $created['product']);
        $this->assertSame('first_ride', $created['audience']);
    }

    public function test_preview_quotes_on_server_and_rejects_client_discount(): void
    {
        $rider = $this->rider();
        $this->actingAs($rider)->postJson('/api/v1/coupons/preview', [
            'code' => 'SAVE10',
            'fare_paise' => 20_000,
            'discount_paise' => 19_000,
        ])->assertStatus(422);

        $this->actingAs($rider)->postJson('/api/v1/coupons/preview', [
            'code' => 'SAVE10',
            'fare_paise' => 20_000,
            'product' => 'LOCAL_CAB',
            'state_id' => 1,
            'district_id' => 10,
        ])->assertOk()
            ->assertJsonPath('discountPaise', 2000)
            ->assertJsonPath('payablePaise', 18_000)
            ->assertJsonPath('clientDiscountIgnored', true);
    }

    public function test_apply_writes_server_discount_onto_booking(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->postJson('/api/v1/coupons/apply', [
            'booking_id' => 1,
            'code' => 'SAVE10',
            'couponDiscountPaise' => 50_000,
        ])->assertStatus(422);

        $applied = $this->actingAs($admin)->postJson('/api/v1/coupons/apply', [
            'booking_id' => 1,
            'code' => 'SAVE10',
        ])->assertOk()->json();

        $this->assertSame(2000, $applied['discountPaise']);
        $this->assertSame(2000, (int) DB::connection('platform')->table('bookings')->where('id', 1)->value('coupon_discount_paise'));
        $this->assertSame(2000, (int) DB::connection('platform')->table('coupon_redemptions')->where('booking_id', 1)->value('discount_paise'));
    }

    public function test_first_ride_and_min_fare_are_enforced(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->postJson('/api/v1/coupons', [
            'code' => 'FIRST50',
            'title' => 'First ride',
            'kind' => 'fixed',
            'amount_paise' => 5000,
            'min_fare_paise' => 15_000,
            'audience' => 'first_ride',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
        ])->assertCreated();

        $this->actingAs($this->rider())->postJson('/api/v1/coupons/preview', [
            'code' => 'FIRST50',
            'fare_paise' => 10_000,
        ])->assertStatus(422);

        DB::connection('platform')->table('bookings')->insert([
            'public_ref' => 'KCDONE',
            'customer_id' => 800,
            'status' => 'COMPLETED',
            'product' => 'LOCAL_CAB',
            'quote_paise' => 20_000,
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->rider())->postJson('/api/v1/coupons/preview', [
            'code' => 'FIRST50',
            'fare_paise' => 20_000,
        ])->assertStatus(422);
    }

    public function test_loyalty_earn_is_idempotent_and_preview_ignores_client_points(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        DB::connection('platform')->table('bookings')->where('id', 1)->update(['status' => 'COMPLETED']);

        $this->actingAs($admin)->postJson('/api/v1/loyalty/earn', [
            'booking_id' => 1,
            'points' => 999,
        ])->assertStatus(422);

        $first = $this->actingAs($admin)->postJson('/api/v1/loyalty/earn', ['booking_id' => 1])
            ->assertOk()
            ->json();
        $this->assertSame(10, $first['awarded']);
        $this->assertSame(10, $first['points']);

        $again = $this->actingAs($admin)->postJson('/api/v1/loyalty/earn', ['booking_id' => 1])->assertOk()->json();
        $this->assertTrue($again['idempotent']);
        $this->assertSame(10, $again['points']);

        $this->actingAs($this->rider())->postJson('/api/v1/loyalty/preview', [
            'fare_paise' => 5000,
            'discount_paise' => 4000,
        ])->assertStatus(422);

        $this->actingAs($this->rider())->postJson('/api/v1/loyalty/preview', ['fare_paise' => 5000])
            ->assertOk()
            ->assertJsonPath('discountPaise', 1000);
    }

    public function test_fleet_cannot_manage_coupons(): void
    {
        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/coupons')->assertForbidden();
        $this->actingAs($fleet)->getJson('/api/v1/coupons')->assertForbidden();
    }

    private function rider(): User
    {
        return User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 800,
            'state_id' => 1,
            'district_id' => 10,
        ]);
    }

    private function seedCoupons(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna']]);
        $db->table('users')->insert([
            'id' => 800,
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Rider',
            'email' => 'coupon-rider@karnacab.local',
            'password_hash' => 'x',
            'state_id' => 1,
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('system_settings')->insert([
            ['key' => 'loyalty_points_per_ride', 'value' => '10'],
            ['key' => 'loyalty_paise_per_point', 'value' => '100'],
        ]);
        $db->table('coupons')->insert([
            'code' => 'SAVE10',
            'title' => 'Ten percent',
            'kind' => 'percent',
            'percent' => 10,
            'amount_paise' => 0,
            'max_discount_paise' => 0,
            'min_fare_paise' => 0,
            'product' => null,
            'state_id' => 1,
            'district_id' => 10,
            'audience' => 'all',
            'usage_limit' => 0,
            'user_limit' => 0,
            'starts_on' => now()->subDay(),
            'ends_on' => now()->addMonth(),
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('bookings')->insert([
            'id' => 1,
            'public_ref' => 'KCCOUPON',
            'customer_id' => 800,
            'district_id' => 10,
            'product' => 'LOCAL_CAB',
            'status' => 'REQUESTED',
            'quote_paise' => 20_000,
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
