<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedNotify();
    }

    public function test_dispatch_uses_all_channels_and_otp_stays_out_of_inbox(): void
    {
        $service = app(NotificationService::class);
        $booked = $service->dispatch(800, 'booking_confirmation', ['ref' => 'KC-LIVE']);
        $this->assertSame('booking_confirmation', $booked['event']);
        $this->assertEqualsCanonicalizing(
            ['in_app', 'push', 'sms', 'email'],
            array_column($booked['deliveries'], 'channel'),
        );
        $this->assertSame('sent', collect($booked['deliveries'])->firstWhere('channel', 'in_app')['status']);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => 800,
            'kind' => 'booking_confirmation',
        ], 'platform');

        $otp = $service->dispatch(800, 'otp', ['code' => '654321'], ['phone' => '9876543210']);
        $this->assertNull(collect($otp['deliveries'])->firstWhere('channel', 'in_app'));
        $this->assertSame('sent', collect($otp['deliveries'])->firstWhere('channel', 'sms')['status']);
        $this->assertFalse(
            DB::connection('platform')->table('user_notifications')->where('kind', 'otp')->where('body', 'like', '%654321%')->exists(),
        );
        $otpLog = DB::connection('platform')->table('notification_deliveries')->where('event', 'otp')->where('channel', 'sms')->first();
        $this->assertSame('OTP dispatched', $otpLog->body);
    }

    public function test_modules_call_the_shared_service(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN, 'nest_user_id' => 50]);
        $this->actingAs($admin)->postJson('/api/v1/wallets/ledger', [
            'owner_type' => 'CUSTOMER',
            'owner_user_id' => 800,
            'direction' => 'CREDIT',
            'amount_paise' => 500,
            'note' => 'Top up',
        ])->assertCreated();
        $this->assertTrue(
            DB::connection('platform')->table('notification_deliveries')->where('event', 'wallet_transaction')->where('user_id', 800)->exists(),
        );

        $this->actingAs($this->rider())->postJson('/api/v1/support/tickets', [
            'subject' => 'Need help please',
            'description' => 'App crashed after payment.',
            'category' => 'other',
        ])->assertCreated();
        $this->assertTrue(
            DB::connection('platform')->table('notification_deliveries')->where('event', 'support_update')->where('user_id', 800)->exists(),
        );
    }

    public function test_admin_workspace_and_fleet_forbidden(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN, 'nest_user_id' => 50]);
        $this->actingAs($admin)->getJson('/api/v1/notifications/catalog')
            ->assertOk()
            ->assertJsonFragment(['channels' => ['in_app', 'push', 'sms', 'email']])
            ->assertJsonFragment(['otp']);
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('Notification system');
        $this->actingAs($admin)->get('/ops/notifications')->assertRedirect(route('notifications.index'));
        $this->actingAs($admin)->postJson('/api/v1/notifications/dispatch', [
            'user_id' => 800,
            'event' => 'payment',
            'vars' => ['ref' => 'PAY1'],
        ])->assertCreated();

        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/notifications')->assertForbidden();
        $this->actingAs($fleet)->postJson('/api/v1/notifications/dispatch', [
            'user_id' => 800,
            'event' => 'payment',
        ])->assertForbidden();
    }

    private function rider(): User
    {
        return User::factory()->create(['role' => OperatorRole::CUSTOMER, 'nest_user_id' => 800, 'district_id' => 10]);
    }

    private function seedNotify(): void
    {
        $db = DB::connection('platform');
        $now = ['created_at' => now(), 'updated_at' => now()];
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna']]);
        $db->table('users')->insert([
            [
                'id' => 50, 'role' => 'ADMIN', 'status' => 'ACTIVE', 'name' => 'Ops',
                'email' => 'notify-ops@karnacab.local', 'password_hash' => 'x', 'phone' => null,
                'district_id' => null,
            ] + $now,
            [
                'id' => 800, 'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Rakesh Rider',
                'email' => 'notify-rider@karnacab.local', 'password_hash' => 'x', 'phone' => '9876543210',
                'district_id' => 10,
            ] + $now,
        ]);
        $db->table('wallets')->insert([
            'owner_type' => 'CUSTOMER', 'owner_user_id' => 800, 'balance_paise' => 0,
        ]);
    }
}
