<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\PaymentLifecycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class PaymentRefundInvoiceTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedPay();
    }

    public function test_admin_opens_payments_module(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->get('/payments')->assertOk()->assertSee('Payments, refunds');
        $this->actingAs($admin)->get('/ops/payments')->assertRedirect(route('payments.index'));
        $this->actingAs($admin)->getJson('/api/v1/payments/catalog')
            ->assertOk()
            ->assertJsonPath('paymentLifecycle.3', 'success')
            ->assertJsonPath('refundLifecycle.0', 'requested');
    }

    public function test_upi_stays_initiated_until_signed_webhook(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $created = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'upi',
            'booking_id' => 1,
        ])->assertCreated()->json();

        $this->assertSame('initiated', $created['status']);
        $this->assertTrue($created['clientCaptureIgnored']);
        $this->assertNotEmpty($created['paymentReference']);

        $this->actingAs($admin)->postJson('/api/v1/payments/'.$created['id'].'/client-success')
            ->assertStatus(422);
        $this->assertSame('initiated', DB::connection('platform')->table('payments')->where('id', $created['id'])->value('status'));

        $this->postJson('/api/v1/payments/webhooks/demo', [
            'event' => 'payment.captured',
            'paymentRef' => $created['paymentReference'],
            'amountPaise' => 1_000_000,
        ])->assertUnauthorized();
        $this->assertSame('initiated', DB::connection('platform')->table('payments')->where('id', $created['id'])->value('status'));

        $signature = PaymentLifecycle::signWebhook(
            'karnacab-dev-pay-hook',
            PaymentLifecycle::webhookCanonical('payment.captured', $created['paymentReference'], 1_000_000),
        );
        $this->withHeaders(['X-Karnacab-Webhook-Signature' => $signature])
            ->postJson('/api/v1/payments/webhooks/demo', [
                'event' => 'payment.captured',
                'paymentRef' => $created['paymentReference'],
                'amountPaise' => 1_000_000,
                'gatewayEventId' => 'evt-1',
                'gatewayPaymentId' => 'pay_abc',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('verifiedSource', 'webhook');

        $this->withHeaders(['X-Karnacab-Webhook-Signature' => $signature])
            ->postJson('/api/v1/payments/webhooks/demo', [
                'event' => 'payment.captured',
                'paymentRef' => $created['paymentReference'],
                'amountPaise' => 1_000_000,
                'gatewayEventId' => 'evt-1',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame('paid', DB::connection('platform')->table('invoices')->where('booking_id', 1)->value('status'));
        $this->assertSame(1_000_000, (int) DB::connection('platform')->table('invoices')->where('booking_id', 1)->value('paid_paise'));
    }

    public function test_duplicate_intent_is_idempotent(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $first = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'card',
            'booking_id' => 1,
        ])->assertCreated()->json('paymentReference');
        $second = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'card',
            'booking_id' => 1,
        ])->assertCreated()->json();
        $this->assertTrue($second['idempotent'] ?? false);
        $this->assertSame($first, $second['paymentReference']);
        $this->assertSame(1, DB::connection('platform')->table('payments')->where('booking_id', 1)->where('kind', 'payment')->count());
    }

    public function test_cash_requires_server_confirm(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $id = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'cash',
            'booking_id' => 1,
        ])->assertCreated()->json('id');
        $this->assertSame('pending', DB::connection('platform')->table('payments')->where('id', $id)->value('status'));
        $this->actingAs($admin)->postJson("/api/v1/payments/{$id}/confirm-cash")
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('verifiedSource', 'cash_confirm');
    }

    public function test_wallet_capture_debits_ledger(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        DB::connection('platform')->table('wallets')->insert([
            'owner_type' => 'CUSTOMER',
            'owner_user_id' => 401,
            'balance_paise' => 2_000_000,
        ]);
        $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'wallet',
            'booking_id' => 1,
        ])->assertCreated()->assertJsonPath('status', 'success');
        $this->assertSame(1_000_000, (int) DB::connection('platform')->table('wallets')->where('owner_user_id', 401)->value('balance_paise'));
    }

    public function test_refund_tracks_requested_to_completed(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $payId = $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'cash',
            'booking_id' => 1,
        ])->json('id');
        $this->actingAs($admin)->postJson("/api/v1/payments/{$payId}/confirm-cash")->assertOk();
        $refund = $this->actingAs($admin)->postJson("/api/v1/payments/{$payId}/refund", [
            'amount_paise' => 400_000,
            'reason' => 'Customer complaint',
        ])->assertCreated()->json();
        $this->assertSame('requested', $refund['status']);
        $this->actingAs($admin)->postJson('/api/v1/payments/'.$refund['id'].'/refund/approved')
            ->assertOk()
            ->assertJsonPath('status', 'approved');
        $this->actingAs($admin)->postJson('/api/v1/payments/'.$refund['id'].'/refund/processing')
            ->assertOk()
            ->assertJsonPath('status', 'processing');
        $this->actingAs($admin)->postJson('/api/v1/payments/'.$refund['id'].'/refund/completed')
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame('partially_refunded', DB::connection('platform')->table('payments')->where('id', $payId)->value('status'));
        $this->assertSame(400_000, (int) DB::connection('platform')->table('invoices')->where('booking_id', 1)->value('refunded_paise'));
        $this->actingAs($admin)->getJson('/api/v1/payments/invoices')->assertOk()->assertJsonPath('invoices.0.status', 'partial');
    }

    public function test_advertiser_cannot_open_payments(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($user)->get('/payments')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/payments')->assertForbidden();
    }

    public function test_client_cannot_post_success_status(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->postJson('/api/v1/payments', [
            'method' => 'upi',
            'booking_id' => 1,
            'status' => 'success',
        ])->assertUnprocessable();
    }

    private function seedPay(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna']]);
        $db->table('users')->insert([
            'id' => 401,
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Rider',
            'email' => 'pay-rider@karnacab.local',
            'password_hash' => 'x',
            'district_id' => 10,
            'state_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('bookings')->insert([
            'id' => 1,
            'public_ref' => 'KCPAY1',
            'customer_id' => 401,
            'district_id' => 10,
            'product' => 'ONE_WAY',
            'status' => 'COMPLETED',
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'quote_paise' => 1_000_000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
