<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class OrganizationHierarchyTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $db = DB::connection('platform');
        $db->table('states')->insert([
            ['id' => 1, 'name' => 'Bihar'],
            ['id' => 2, 'name' => 'Uttar Pradesh'],
        ]);
        $db->table('districts')->insert([
            ['id' => 10, 'state_id' => 1, 'name' => 'Saharsa', 'status' => 'ACTIVE'],
            ['id' => 11, 'state_id' => 1, 'name' => 'Madhepura', 'status' => 'ACTIVE'],
            ['id' => 20, 'state_id' => 2, 'name' => 'Lucknow', 'status' => 'ACTIVE'],
        ]);
    }

    public function test_manager_only_receives_assigned_permissions(): void
    {
        $manager = User::factory()->create(['role' => OperatorRole::MANAGER]);
        $manager->syncAbilities(['fleet.view', 'fleet_owner.view']);

        $this->assertTrue($manager->fresh()->can('fleet.view'));
        $this->assertTrue($manager->fresh()->can('fleet_owner.view'));
        $this->assertFalse($manager->fresh()->can('fleet.manage'));
        $this->assertFalse($manager->fresh()->can('fleet_owner.create'));
        $this->assertFalse($manager->fresh()->can('platform.admin'));

        $this->actingAs($manager)->get('/fleet-owners/create')->assertForbidden();
        $this->actingAs($manager)->get('/fleet-owners')->assertOk();
    }

    public function test_second_active_state_head_for_same_state_is_blocked(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $aman = User::factory()->create(['role' => OperatorRole::STATE_HEAD, 'status' => 'ACTIVE']);
        $other = User::factory()->create(['role' => OperatorRole::STATE_HEAD, 'status' => 'ACTIVE']);

        $this->actingAs($admin)->put('/state-assignments/'.$aman->id, ['state_id' => 1])->assertRedirect();
        $this->assertSame(1, $aman->fresh()->state_id);

        $this->actingAs($admin)
            ->from('/state-assignments')
            ->put('/state-assignments/'.$other->id, ['state_id' => 1])
            ->assertRedirect('/state-assignments');
        $this->assertNull($other->fresh()->state_id);

        $this->actingAs($admin)
            ->put('/state-assignments/'.$other->id, ['state_id' => 1, 'transfer' => 1])
            ->assertRedirect();
        $this->assertSame(1, $other->fresh()->state_id);
        $this->assertSame('SUSPENDED', $aman->fresh()->status);
    }

    public function test_state_head_cannot_open_foreign_state_workspace(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->getJson('/api/v1/state?stateId=2')
            ->assertOk()
            ->assertJsonPath('state.stateId', 1);
    }

    public function test_multiple_fleet_owners_allowed_in_one_district(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $this->actingAs($admin)->post('/fleet-owners', [
            'name' => 'Fleet A',
            'trade_name' => 'A Cabs',
            'email' => 'a-fleet@karnacab.local',
            'password' => 'ChangeMe@123',
            'state_id' => 1,
            'district_id' => 10,
        ])->assertRedirect();
        $this->actingAs($admin)->post('/fleet-owners', [
            'name' => 'Fleet B',
            'trade_name' => 'B Cabs',
            'email' => 'b-fleet@karnacab.local',
            'password' => 'ChangeMe@123',
            'state_id' => 1,
            'district_id' => 10,
        ])->assertRedirect();

        $this->assertSame(2, DB::connection('platform')->table('fleet_owners')->where('district_id', 10)->count());
    }

    public function test_individual_driver_has_null_fleet_owner(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $this->actingAs($admin)->post('/drivers', [
            'name' => 'Danish',
            'email' => 'danish@karnacab.local',
            'phone' => '9990001111',
            'status' => 'ACTIVE',
            'license_no' => 'BR-1',
            'kyc_status' => 'pending',
            'password' => 'ChangeMe@123',
            'driver_type' => 'individual_driver',
            'state_id' => 1,
            'district_id' => 10,
        ])->assertRedirect();

        $driver = DB::connection('platform')->table('drivers')->orderByDesc('id')->first();
        $this->assertNull($driver->fleet_owner_id);
        $this->assertSame('individual_driver', $driver->driver_type);
    }
}
