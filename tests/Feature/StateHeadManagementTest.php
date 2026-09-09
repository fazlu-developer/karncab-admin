<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class StateHeadManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedTerritory();
    }

    public function test_state_head_dashboard_only_includes_assigned_state(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->get('/state')
            ->assertOk()
            ->assertSee('Bihar')
            ->assertSee('Patna')
            ->assertDontSee('Ranchi');

        $this->actingAs($head)
            ->getJson('/api/v1/state?stateId=2')
            ->assertOk()
            ->assertJsonPath('state.stateId', 1)
            ->assertJsonPath('kpis.districts', 1);
    }

    public function test_state_api_bookings_ignore_foreign_state_parameter(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->getJson('/api/v1/state/bookings?stateId=2')
            ->assertOk()
            ->assertJsonFragment(['publicRef' => 'KCMINE'])
            ->assertJsonMissing(['publicRef' => 'KCOTHER']);
    }

    public function test_district_head_cannot_be_created_outside_state(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->postJson('/api/v1/state/district-heads', [
                'name' => 'Outsider',
                'email' => 'out@karnacab.local',
                'district_id' => 20,
                'password' => 'ChangeMe@123',
            ])
            ->assertStatus(422);
    }

    public function test_unassigned_state_head_sees_assignment_message(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => null,
        ]);

        $this->actingAs($head)
            ->get('/state')
            ->assertOk()
            ->assertSee('No state assigned');
    }

    public function test_admin_can_assign_state_and_advertiser_cannot_open_workspace(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'email' => 'assign-me@karnacab.local',
            'state_id' => null,
        ]);

        $this->actingAs($admin)
            ->put('/state-assignments/'.$head->id, ['state_id' => 1])
            ->assertRedirect();

        $this->assertSame(1, $head->fresh()->state_id);

        $ads = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($ads)->getJson('/api/v1/state')->assertForbidden();
    }

    public function test_state_head_login_opens_state_workspace(): void
    {
        $head = User::factory()->create([
            'email' => 'bihar-head@karnacab.local',
            'password' => 'ChangeMe@123',
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->post('/login', [
            'email' => 'bihar-head@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->assertRedirect('/state');

        $this->assertAuthenticatedAs($head);
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
            'password_hash' => 'x',
            'state_id' => 1,
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $other = $db->table('users')->insertGetId([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Ranchi Rider',
            'email' => 'ranchi@karnacab.local',
            'password_hash' => 'x',
            'state_id' => 2,
            'district_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
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
                'quote_paise' => 50000,
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
                'quote_paise' => 90000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
