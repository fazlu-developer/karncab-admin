<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class WalletCommissionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedFinance();
    }

    public function test_admin_opens_wallets_and_commission_screens(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($admin)->get('/wallets')->assertOk()->assertSee('Wallets & ledger', false);
        $this->actingAs($admin)->get('/wallets/commission')->assertOk()->assertSee('Commission engine');
        $this->actingAs($admin)->get('/ops/wallets')->assertRedirect(route('wallets.index'));
        $this->actingAs($admin)->get('/ops/commission')->assertRedirect(route('wallets.commission'));
    }

    public function test_settle_uses_database_rule_ten_percent_of_ten_thousand(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $bookingId = $this->insertCompletedBooking(1_000_000);

        $this->actingAs($admin)
            ->postJson("/api/v1/bookings/{$bookingId}/settle")
            ->assertOk()
            ->assertJsonPath('settlement.commissionPaise', 100_000)
            ->assertJsonPath('settlement.netPaise', 900_000);

        $driverCredit = DB::connection('platform')->table('wallet_ledger')
            ->where('booking_id', $bookingId)
            ->where('account', 'DRIVER')
            ->where('direction', 'CREDIT')
            ->first();
        $this->assertNotNull($driverCredit);
        $this->assertSame(900_000, (int) $driverCredit->amount_paise);
        $this->assertSame(100_000, (int) $driverCredit->commission_paise);
        $this->assertNotEmpty($driverCredit->public_ref);
        $this->assertSame('PAY-TEN-K', $driverCredit->payment_ref);
        $this->assertSame(0, (int) $driverCredit->balance_before_paise);
        $this->assertSame(900_000, (int) $driverCredit->balance_after_paise);

        $platform = DB::connection('platform')->table('wallet_ledger')
            ->where('booking_id', $bookingId)
            ->where('account', 'PLATFORM')
            ->first();
        $this->assertNotNull($platform);
        $this->assertSame(100_000, (int) $platform->amount_paise);
    }

    public function test_changing_on_toll_changes_eligible_commission(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        DB::connection('platform')->table('commission_rules')->where('name', 'default')->update([
            'on_toll' => 1,
            'percent' => 10,
        ]);
        $bookingId = $this->insertCompletedBooking(1_000_000, [
            'breakdown' => [
                'basePaise' => 800_000,
                'tollPaise' => 200_000,
            ],
            'totalPaise' => 1_000_000,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/bookings/{$bookingId}/settle")->assertOk()
            ->assertJsonPath('settlement.commissionPaise', 100_000);
    }

    public function test_toll_excluded_when_flag_off(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $bookingId = $this->insertCompletedBooking(1_000_000, [
            'breakdown' => [
                'basePaise' => 800_000,
                'tollPaise' => 200_000,
            ],
            'totalPaise' => 1_000_000,
        ]);

        $this->actingAs($admin)->postJson("/api/v1/bookings/{$bookingId}/settle")->assertOk()
            ->assertJsonPath('settlement.commissionPaise', 80_000)
            ->assertJsonPath('settlement.netPaise', 920_000);
    }

    public function test_double_settle_does_not_double_credit(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $bookingId = $this->insertCompletedBooking(1_000_000);

        $this->actingAs($admin)->postJson("/api/v1/bookings/{$bookingId}/settle")->assertOk();
        $this->actingAs($admin)->postJson("/api/v1/bookings/{$bookingId}/settle")->assertOk();

        $this->assertSame(1, DB::connection('platform')->table('wallet_ledger')
            ->where('booking_id', $bookingId)
            ->where('account', 'DRIVER')
            ->where('kind', 'trip')
            ->count());
        $this->assertSame(900_000, (int) DB::connection('platform')->table('wallets')
            ->where('owner_type', 'DRIVER')
            ->where('owner_user_id', 501)
            ->value('balance_paise'));
    }

    public function test_debit_without_balance_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->postJson('/api/v1/wallets/ledger', [
            'owner_type' => 'CUSTOMER',
            'owner_user_id' => 401,
            'direction' => 'DEBIT',
            'amount_paise' => 50,
            'kind' => 'adjustment',
        ])->assertStatus(400);
    }

    public function test_fleet_owner_cannot_see_another_fleets_wallet(): void
    {
        $mine = User::factory()->create([
            'role' => OperatorRole::FLEET_OWNER,
            'fleet_owner_id' => 1,
            'district_id' => 10,
            'state_id' => 1,
        ]);
        $otherWallet = (int) DB::connection('platform')->table('wallets')
            ->where('owner_type', 'FLEET_OWNER')
            ->where('owner_user_id', 202)
            ->value('id');
        $ownWallet = (int) DB::connection('platform')->table('wallets')
            ->where('owner_type', 'FLEET_OWNER')
            ->where('owner_user_id', 201)
            ->value('id');

        $this->actingAs($mine)->getJson("/api/v1/wallets/{$otherWallet}")->assertNotFound();
        $this->actingAs($mine)->getJson("/api/v1/wallets/{$ownWallet}")->assertOk();
        $list = $this->actingAs($mine)->getJson('/api/v1/wallets')->assertOk()->json('wallets');
        $ownerIds = array_column($list, 'ownerUserId');
        $this->assertContains('201', $ownerIds);
        $this->assertNotContains('202', $ownerIds);
    }

    public function test_advertiser_cannot_open_wallets(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($user)->get('/wallets')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/wallets')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/commission')->assertForbidden();
    }

    public function test_admin_can_change_commission_percent_on_the_rule(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->patchJson('/api/v1/commission', [
            'percent' => 12.5,
            'on_base_fare' => true,
            'on_gst' => false,
            'on_toll' => false,
            'on_parking' => false,
            'on_waiting' => false,
            'on_discount' => false,
            'on_other' => false,
            'on_complete' => false,
        ])->assertOk()->assertJsonPath('percent', 12.5);

        $this->assertEquals(12.5, (float) DB::connection('platform')->table('commission_rules')->where('name', 'default')->value('percent'));
        $source = file_get_contents(app_path('Services/FleetOwnerService.php'));
        $this->assertStringNotContainsString('?? 10', $source);
        $this->assertStringNotContainsString('?? 10', file_get_contents(app_path('Services/WalletLedgerService.php')));
    }

    public function test_ledger_post_records_all_required_fields(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $payload = $this->actingAs($admin)->postJson('/api/v1/wallets/ledger', [
            'owner_type' => 'CUSTOMER',
            'owner_user_id' => 401,
            'direction' => 'CREDIT',
            'amount_paise' => 25000,
            'commission_paise' => 0,
            'kind' => 'adjustment',
            'payment_ref' => 'UPI-REF-9',
            'note' => 'Top-up',
        ])->assertCreated()->json();

        $this->assertNotEmpty($payload['transactionId']);
        $this->assertSame('401', $payload['userId']);
        $this->assertSame('CREDIT', $payload['direction']);
        $this->assertSame(25000, $payload['amountPaise']);
        $this->assertSame('UPI-REF-9', $payload['paymentReference']);
        $this->assertSame('posted', $payload['status']);
        $this->assertSame(0, $payload['previousBalancePaise']);
        $this->assertSame(25000, $payload['newBalancePaise']);
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    private function insertCompletedBooking(int $quotePaise, ?array $snapshot = null): int
    {
        $db = DB::connection('platform');
        $id = $db->table('bookings')->insertGetId([
            'public_ref' => 'KCWAL'.random_int(1000, 9999),
            'customer_id' => 401,
            'driver_id' => 1,
            'vehicle_id' => 1,
            'district_id' => 10,
            'product' => 'ONE_WAY',
            'category' => 'SEDAN',
            'status' => 'COMPLETED',
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'quote_paise' => $quotePaise,
            'quote_snapshot' => $snapshot ? json_encode($snapshot) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('payments')->insert([
            'public_ref' => 'PAY-TEN-K',
            'method' => 'upi',
            'amount_paise' => $quotePaise,
            'status' => 'captured',
            'customer_id' => 401,
            'booking_id' => $id,
        ]);

        return $id;
    }

    private function seedFinance(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar'], ['id' => 2, 'name' => 'Jharkhand']]);
        $db->table('districts')->insert([
            ['id' => 10, 'state_id' => 1, 'name' => 'Patna'],
            ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi'],
        ]);
        $db->table('users')->insert([
            ['id' => 100, 'role' => 'ADMIN', 'status' => 'ACTIVE', 'name' => 'Platform', 'email' => 'platform@karnacab.local', 'password_hash' => 'x', 'district_id' => null, 'state_id' => null, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 201, 'role' => 'FLEET_OWNER', 'status' => 'ACTIVE', 'name' => 'Patna Owner', 'email' => 'fo1@karnacab.local', 'password_hash' => 'x', 'district_id' => 10, 'state_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 202, 'role' => 'FLEET_OWNER', 'status' => 'ACTIVE', 'name' => 'Ranchi Owner', 'email' => 'fo2@karnacab.local', 'password_hash' => 'x', 'district_id' => 20, 'state_id' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 401, 'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Rider', 'email' => 'rider@karnacab.local', 'password_hash' => 'x', 'district_id' => 10, 'state_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 501, 'role' => 'DRIVER', 'status' => 'ACTIVE', 'name' => 'Driver One', 'email' => 'drv1@karnacab.local', 'password_hash' => 'x', 'district_id' => 10, 'state_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 502, 'role' => 'DRIVER', 'status' => 'ACTIVE', 'name' => 'Driver Two', 'email' => 'drv2@karnacab.local', 'password_hash' => 'x', 'district_id' => 20, 'state_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('fleet_owners')->insert([
            ['id' => 1, 'user_id' => 201, 'trade_name' => 'Patna Cabs'],
            ['id' => 2, 'user_id' => 202, 'trade_name' => 'Ranchi Motors'],
        ]);
        $db->table('drivers')->insert([
            ['id' => 1, 'user_id' => 501, 'fleet_owner_id' => 1, 'online' => 0, 'kyc_status' => 'verified', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'user_id' => 502, 'fleet_owner_id' => 2, 'online' => 0, 'kyc_status' => 'verified', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('vehicles')->insert([
            ['id' => 1, 'registration_no' => 'BR01WA0001', 'status' => 'AVAILABLE', 'category' => 'SEDAN', 'district_id' => 10, 'fleet_owner_id' => 1, 'driver_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'registration_no' => 'JH01WA0002', 'status' => 'AVAILABLE', 'category' => 'SEDAN', 'district_id' => 20, 'fleet_owner_id' => 2, 'driver_id' => 2, 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('wallets')->insert([
            ['owner_type' => 'FLEET_OWNER', 'owner_user_id' => 201, 'balance_paise' => 0],
            ['owner_type' => 'FLEET_OWNER', 'owner_user_id' => 202, 'balance_paise' => 0],
            ['owner_type' => 'PLATFORM', 'owner_user_id' => 100, 'balance_paise' => 0],
        ]);
        $db->table('commission_rules')->insert([
            'name' => 'default',
            'percent' => 10,
            'active' => 1,
            'on_base_fare' => 1,
            'on_gst' => 0,
            'on_toll' => 0,
            'on_parking' => 0,
            'on_waiting' => 0,
            'on_discount' => 0,
            'on_other' => 0,
            'on_complete' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('system_settings')->insert([
            ['key' => 'fleet_commission_share_percent', 'value' => '0'],
            ['key' => 'platform_wallet_user_id', 'value' => '100'],
        ]);
    }
}
