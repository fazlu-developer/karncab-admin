<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Services\AclCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class RoleAccessCommissionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $db = DB::connection('platform');
        $db->table('states')->insert([
            ['id' => 1, 'name' => 'Bihar', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Delhi', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('districts')->insert([
            ['id' => 11, 'state_id' => 1, 'name' => 'Saharsa', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30, 'state_id' => 2, 'name' => 'New Delhi', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $db->table('users')->insert([
            [
                'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Saharsa Rider', 'email' => 'saharsa-rider@karnacab.local',
                'password_hash' => 'x', 'state_id' => 1, 'district_id' => 11, 'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
        $customerId = (int) $db->table('users')->where('email', 'saharsa-rider@karnacab.local')->value('id');
        $db->table('bookings')->insert([
            [
                'public_ref' => 'SAHARSA1', 'customer_id' => $customerId, 'district_id' => 11, 'product' => 'ONE_WAY',
                'status' => 'COMPLETED', 'pickup_text' => 'Saharsa', 'drop_text' => 'Saharsa bus stand', 'quote_paise' => 100000,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'public_ref' => 'DELHI1', 'customer_id' => $customerId, 'district_id' => 30, 'product' => 'ONE_WAY',
                'status' => 'COMPLETED', 'pickup_text' => 'Delhi', 'drop_text' => 'Connaught Place', 'quote_paise' => 200000,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }

    public function test_super_admin_sees_vehicles_and_admin_without_ability_is_forbidden(): void
    {
        $super = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $this->actingAs($super)->get('/dashboard')->assertOk()->assertSee('Vehicles', false);
        $this->actingAs($super)->get('/vehicles')->assertOk();

        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        AclCatalog::catalog();
        $kept = array_values(array_filter(
            AclCatalog::forRole(OperatorRole::ADMIN) ?? [],
            fn (string $ability) => ! in_array($ability, ['vehicles.view', 'tracking.view'], true),
        ));
        AclCatalog::saveRole(OperatorRole::ADMIN, $kept);

        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertDontSee(route('vehicles.index'), false);
        $this->actingAs($admin)->get('/vehicles')->assertForbidden();
    }

    public function test_state_head_cannot_open_another_state(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $this->actingAs($head)
            ->get('/state?stateId=2')
            ->assertOk()
            ->assertSee('Bihar')
            ->assertSee('Saharsa')
            ->assertDontSee('New Delhi');
    }

    public function test_district_head_sees_only_saharsa_and_can_add_a_fleet_there(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::DISTRICT_HEAD,
            'state_id' => 1,
            'district_id' => 11,
        ]);

        $this->actingAs($head)
            ->get('/district?districtId=30')
            ->assertOk()
            ->assertSee('Saharsa')
            ->assertDontSee('New Delhi');

        $this->actingAs($head)
            ->get('/ops/bookings?districtId=30')
            ->assertOk()
            ->assertSee('SAHARSA1')
            ->assertDontSee('DELHI1');

        $this->actingAs($head)->post('/fleet-owners', [
            'name' => 'Delhi Owner',
            'trade_name' => 'Delhi Cabs',
            'email' => 'delhi-fleet@karnacab.local',
            'password' => 'ChangeMe@123',
            'state_id' => 2,
            'district_id' => 30,
        ])->assertForbidden();

        $this->actingAs($head)->post('/fleet-owners', [
            'name' => 'Saharsa Owner',
            'trade_name' => 'Saharsa Cabs',
            'email' => 'saharsa-fleet@karnacab.local',
            'password' => 'ChangeMe@123',
            'state_id' => 1,
            'district_id' => 11,
        ])->assertRedirect();

        $fleetUser = DB::connection('platform')->table('users')->where('email', 'saharsa-fleet@karnacab.local')->first();
        $this->assertNotNull($fleetUser);
        $this->assertSame(11, (int) $fleetUser->district_id);

        $this->actingAs($head)->post('/drivers', [
            'name' => 'Saharsa Driver',
            'email' => 'saharsa-driver@karnacab.local',
            'status' => 'ACTIVE',
            'license_no' => 'BR11DH001',
            'kyc_status' => 'pending',
            'password' => 'ChangeMe@123',
            'state_id' => 2,
            'district_id' => 30,
        ])->assertRedirect();

        $driverUser = DB::connection('platform')->table('users')->where('email', 'saharsa-driver@karnacab.local')->first();
        $this->assertNotNull($driverUser);
        $this->assertSame(11, (int) $driverUser->district_id);
        $this->assertSame(1, (int) $driverUser->state_id);
    }

    public function test_second_active_district_head_for_the_same_district_is_rejected(): void
    {
        DB::connection('platform')->table('users')->insert([
            'role' => 'DISTRICT_HEAD',
            'status' => 'ACTIVE',
            'name' => 'First Head',
            'email' => 'first-dh@karnacab.local',
            'password_hash' => 'x',
            'state_id' => 1,
            'district_id' => 11,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Second Head',
            'email' => 'second-dh@karnacab.local',
            'role' => 'DISTRICT_HEAD',
            'status' => 'ACTIVE',
            'password' => 'ChangeMe@123',
            'state_id' => 1,
            'district_id' => 11,
        ])->assertStatus(409);
    }

    public function test_creating_a_state_head_opens_an_admin_login_and_assigns_the_state(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($admin)->post('/users', [
            'name' => 'Kaif',
            'email' => 'kaif@gmail.com',
            'phone' => '1234567890',
            'role' => 'STATE_HEAD',
            'status' => 'ACTIVE',
            'password' => 'ChangeMe@123',
            'state_id' => 2,
            'district_id' => 30,
            'kyc_pan' => \Illuminate\Http\UploadedFile::fake()->create('pan.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $operator = User::query()->where('email', 'kaif@gmail.com')->first();
        $this->assertNotNull($operator);
        $this->assertSame(OperatorRole::STATE_HEAD, $operator->role);
        $this->assertSame(2, (int) $operator->state_id);
        $this->assertNull($operator->district_id);

        $this->actingAs($admin)->get('/state-assignments')->assertOk()->assertSee('Kaif')->assertSee('kaif@gmail.com');
        $this->assertTrue(\Illuminate\Support\Facades\Schema::connection('platform')->hasTable('state_head_kyc'));
        $this->assertSame(1, DB::connection('platform')->table('state_head_kyc')->where('doc_type', 'PAN')->count());
        $platformId = (int) DB::connection('platform')->table('users')->where('email', 'kaif@gmail.com')->value('id');
        $this->actingAs($admin)->get('/organization/state-kyc?user_id='.$platformId)
            ->assertOk()
            ->assertSee('Kaif')
            ->assertSee('PAN')
            ->assertSee('pan.pdf')
            ->assertSee('Delhi');

        \Illuminate\Support\Facades\Auth::logout();
        $this->post('/login', [
            'email' => 'kaif@gmail.com',
            'password' => 'ChangeMe@123',
        ])->assertRedirect();
        $this->assertAuthenticatedAs($operator);
    }

    public function test_state_assignment_page_lists_platform_state_heads_and_can_move_them(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);
        $platformId = DB::connection('platform')->table('users')->insertGetId([
            'role' => 'STATE_HEAD',
            'status' => 'ACTIVE',
            'name' => 'Kaif',
            'email' => 'kaif-platform@karnacab.local',
            'password_hash' => 'x',
            'state_id' => 2,
            'district_id' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)->get('/state-assignments')
            ->assertOk()
            ->assertSee('Kaif')
            ->assertSee('kaif-platform@karnacab.local');

        $operator = User::query()->where('email', 'kaif-platform@karnacab.local')->first();
        $this->assertNotNull($operator);
        $this->assertSame(2, (int) $operator->state_id);

        $this->actingAs($admin)->put('/state-assignments/'.$operator->id, [
            'state_id' => 1,
            'transfer' => 0,
        ])->assertRedirect();

        $this->assertSame(1, (int) $operator->fresh()->state_id);
        $this->assertSame(1, (int) DB::connection('platform')->table('users')->where('id', $platformId)->value('state_id'));
        $this->assertNull(DB::connection('platform')->table('users')->where('id', $platformId)->value('district_id'));
    }
}
