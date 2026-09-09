<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedSupport();
    }

    public function test_user_can_create_ticket_with_required_fields(): void
    {
        $rider = $this->rider();
        $payload = $this->actingAs($rider)->postJson('/api/v1/support/tickets', [
            'subject' => 'Driver was rude',
            'description' => 'The driver raised their voice at pickup.',
            'category' => 'driver',
            'priority' => 'high',
            'kind' => 'complaint',
            'booking_id' => 1,
        ])->assertCreated()->json();

        $this->assertNotEmpty($payload['ticketId']);
        $this->assertSame('open', $payload['status']);
        $this->assertSame('driver', $payload['category']);
        $this->assertSame('high', $payload['priority']);
        $this->assertSame('Driver was rude', $payload['subject']);
        $this->assertStringContainsString('raised their voice', $payload['description']);
        $this->assertSame(800, $payload['user']['id']);
        $this->assertSame('KC-LIVE', $payload['booking']['publicRef']);
        $this->assertNull($payload['closedAt']);

        $row = DB::connection('platform')->table('support_tickets')->where('id', $payload['id'])->first();
        $this->assertSame(800, (int) $row->user_id);
        $this->assertSame(1, (int) $row->booking_id);
        $this->assertSame(10, (int) $row->district_id);
        $this->assertNotEmpty($row->created_at);
    }

    public function test_me_complaint_and_attachments_and_isolation(): void
    {
        $rider = $this->rider();
        $jpeg = UploadedFile::fake()->image('photo.jpg', 20, 20);
        $created = $this->actingAs($rider)->post('/api/v1/me/complaints', [
            'subject' => 'AC not working',
            'description' => 'Cabin was too warm the whole trip.',
            'category' => 'booking',
            'priority' => 'medium',
            'booking_id' => 1,
            'file' => $jpeg,
        ], ['Accept' => 'application/json'])->assertCreated()->json();

        $this->assertSame('open', $created['status']);
        $this->actingAs($rider)->getJson('/api/v1/support/tickets/'.$created['id'])
            ->assertOk()
            ->assertJsonPath('attachments.0.mime', 'image/jpeg');

        $pdf = UploadedFile::fake()->create('note.pdf', 12, 'application/pdf');
        $this->actingAs($rider)->post('/api/v1/support/tickets/'.$created['id'].'/attachments', [
            'file' => $pdf,
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('mime', 'application/pdf');

        $other = User::factory()->create(['role' => OperatorRole::CUSTOMER, 'nest_user_id' => 801]);
        $this->actingAs($other)->getJson('/api/v1/support/tickets/'.$created['id'])->assertNotFound();
        $this->actingAs($other)->getJson('/api/v1/support/tickets')->assertOk()
            ->assertJsonPath('tickets', []);
    }

    public function test_admin_history_and_status_flow(): void
    {
        $rider = $this->rider();
        $id = $this->actingAs($rider)->postJson('/api/v1/support/tickets', [
            'subject' => 'Fare dispute',
            'description' => 'Charged more than the quote shown.',
            'category' => 'payment',
            'priority' => 'urgent',
        ])->assertCreated()->json('id');

        $admin = User::factory()->create(['role' => OperatorRole::ADMIN, 'nest_user_id' => 50]);

        $this->actingAs($admin)->patchJson('/api/v1/support/tickets/'.$id, [
            'status' => 'resolved',
            'resolution' => 'Refund issued',
        ])->assertStatus(400);

        $this->actingAs($admin)->postJson('/api/v1/support/tickets/'.$id.'/assign')
            ->assertOk()
            ->assertJsonPath('status', 'assigned')
            ->assertJsonPath('assignedAgent.id', 50);

        $this->actingAs($admin)->patchJson('/api/v1/support/tickets/'.$id, [
            'status' => 'in_progress',
        ])->assertOk()->assertJsonPath('status', 'in_progress');

        $this->actingAs($admin)->patchJson('/api/v1/support/tickets/'.$id, [
            'status' => 'resolved',
        ])->assertStatus(400);

        $this->actingAs($admin)->patchJson('/api/v1/support/tickets/'.$id, [
            'status' => 'resolved',
            'resolution' => 'Quote restored and difference refunded to wallet.',
        ])->assertOk()->assertJsonPath('status', 'resolved');

        $closed = $this->actingAs($admin)->patchJson('/api/v1/support/tickets/'.$id, [
            'status' => 'closed',
        ])->assertOk()->json();

        $this->assertSame('closed', $closed['status']);
        $this->assertNotNull($closed['closedAt']);
        $this->assertNotEmpty($closed['history']);
        $this->assertTrue(collect($closed['history'])->contains(fn ($item) => ($item['action'] ?? null) === 'created'));
        $this->assertTrue(collect($closed['history'])->contains(fn ($item) => ($item['toStatus'] ?? null) === 'closed'));

        $this->actingAs($admin)->get('/support')->assertOk()->assertSee('Fare dispute');
        $this->actingAs($admin)->get('/support/'.$id)->assertOk()->assertSee('History')->assertSee('Quote restored');
        $this->actingAs($admin)->get('/ops/complaints')->assertRedirect(route('support.index'));
    }

    public function test_ops_workspace_is_territory_scoped_and_fleet_is_forbidden(): void
    {
        $this->actingAs($this->rider())->postJson('/api/v1/support/tickets', [
            'subject' => 'Lost item',
            'description' => 'Left a bag on the back seat.',
            'category' => 'other',
        ])->assertCreated();

        $admin = User::factory()->create(['role' => OperatorRole::ADMIN, 'nest_user_id' => 50]);
        $this->actingAs($admin)->getJson('/api/v1/support/tickets')->assertOk()
            ->assertJsonFragment(['subject' => 'Lost item']);

        $head = User::factory()->create(['role' => OperatorRole::DISTRICT_HEAD, 'district_id' => 20, 'state_id' => 2]);
        $this->actingAs($head)->getJson('/api/v1/support/tickets')->assertOk()
            ->assertJsonPath('tickets', []);

        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/support')->assertForbidden();
        $this->actingAs($fleet)->postJson('/api/v1/support/tickets', [
            'subject' => 'Fleet cannot open this',
            'description' => 'Should be blocked for fleet owners.',
        ])->assertForbidden();
    }

    private function rider(): User
    {
        return User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 800,
            'district_id' => 10,
        ]);
    }

    private function seedSupport(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar'], ['id' => 2, 'name' => 'Jharkhand']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna'], ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi']]);
        $now = ['created_at' => now(), 'updated_at' => now()];
        $db->table('users')->insert([
            [
                'id' => 50, 'role' => 'ADMIN', 'status' => 'ACTIVE', 'name' => 'Ops Agent',
                'email' => 'ops-agent@karnacab.local', 'password_hash' => 'x',
                'district_id' => null,
            ] + $now,
            [
                'id' => 800, 'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Rakesh Rider',
                'email' => 'support-rider@karnacab.local', 'password_hash' => 'x',
                'district_id' => 10,
            ] + $now,
        ]);
        $db->table('bookings')->insert([
            [
                'id' => 1, 'public_ref' => 'KC-LIVE', 'customer_id' => 800, 'district_id' => 10,
                'status' => 'COMPLETED', 'product' => 'LOCAL_CAB',
                'pickup_text' => 'Patna Jn', 'drop_text' => 'Gandhi Maidan',
            ] + $now,
        ]);
    }
}
