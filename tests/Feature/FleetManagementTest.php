<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedFleets();
    }

    public function test_fleet_owner_login_opens_fleet_workspace(): void
    {
        $owner = User::factory()->create([
            'email' => 'fleet-login@karnacab.local',
            'password' => 'ChangeMe@123',
            'role' => OperatorRole::FLEET_OWNER,
            'fleet_owner_id' => 1,
            'district_id' => 10,
        ]);

        $this->post('/login', [
            'email' => 'fleet-login@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->assertRedirect('/fleet');

        $this->actingAs($owner)
            ->get('/fleet')
            ->assertOk()
            ->assertSee('Patna Cabs')
            ->assertDontSee('Ranchi Motors');
    }

    public function test_fleet_cannot_see_or_edit_another_fleets_vehicle(): void
    {
        $owner = $this->owner(1);
        $other = $this->owner(2);
        $mine = $this->actingAs($owner)
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_no' => 'BR01AB1111',
                'category' => 'SEDAN',
                'district_id' => 10,
            ])
            ->assertCreated()
            ->json('id');

        $theirs = $this->actingAs($other)
            ->postJson('/api/v1/fleet/vehicles', [
                'registration_no' => 'JH01CD2222',
                'category' => 'SUV',
                'district_id' => 20,
            ])
            ->assertCreated()
            ->json('id');

        $this->actingAs($owner)->getJson("/api/v1/fleet/vehicles/{$theirs}")->assertNotFound();
        $this->actingAs($owner)->patchJson("/api/v1/fleet/vehicles/{$theirs}", ['color' => 'red'])->assertNotFound();
        $this->actingAs($owner)->getJson('/api/v1/fleet/vehicles')->assertJsonMissing(['registrationNo' => 'JH01CD2222']);
        $this->actingAs($owner)->getJson("/api/v1/fleet/vehicles/{$mine}")->assertOk()->assertJsonPath('registrationNo', 'BR01AB1111');
    }

    public function test_duplicate_registration_is_rejected(): void
    {
        $owner = $this->owner(1);
        $this->actingAs($owner)->postJson('/api/v1/fleet/vehicles', [
            'registration_no' => 'BR09XX0001',
            'category' => 'MINI',
            'district_id' => 10,
        ])->assertCreated();

        $this->actingAs($owner)->postJson('/api/v1/fleet/vehicles', [
            'registration_no' => 'br09xx0001',
            'category' => 'MINI',
            'district_id' => 10,
        ])->assertStatus(409);
    }

    public function test_cannot_assign_driver_from_another_fleet_or_to_maintenance_vehicle(): void
    {
        $owner = $this->owner(1);
        $other = $this->owner(2);
        $vehicleId = $this->actingAs($owner)->postJson('/api/v1/fleet/vehicles', [
            'registration_no' => 'BR01AS3333',
            'category' => 'SEDAN',
            'district_id' => 10,
        ])->json('id');
        $driverId = $this->actingAs($owner)->postJson('/api/v1/fleet/drivers', [
            'name' => 'Ravi',
            'email' => 'ravi@karnacab.local',
            'password' => 'ChangeMe@123',
            'license_no' => 'BR-LIC-1',
        ])->json('id');
        $foreignDriver = $this->actingAs($other)->postJson('/api/v1/fleet/drivers', [
            'name' => 'Amit',
            'email' => 'amit@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->json('id');

        $this->actingAs($owner)
            ->postJson("/api/v1/fleet/drivers/{$foreignDriver}/assign", ['vehicle_id' => $vehicleId])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson("/api/v1/fleet/drivers/{$driverId}/assign", ['vehicle_id' => $vehicleId])
            ->assertOk()
            ->assertJsonPath('assignedVehicle.registrationNo', 'BR01AS3333');

        $this->actingAs($owner)
            ->postJson("/api/v1/fleet/drivers/{$driverId}/unassign")
            ->assertOk();
        $this->actingAs($owner)
            ->patchJson("/api/v1/fleet/vehicles/{$vehicleId}", ['status' => 'maintenance'])
            ->assertOk()
            ->assertJsonPath('status', 'maintenance');
        $this->actingAs($owner)
            ->postJson("/api/v1/fleet/drivers/{$driverId}/assign", ['vehicle_id' => $vehicleId])
            ->assertStatus(422);
    }

    public function test_cannot_set_on_trip_manually_or_verify_kyc(): void
    {
        $owner = $this->owner(1);
        $vehicleId = $this->actingAs($owner)->postJson('/api/v1/fleet/vehicles', [
            'registration_no' => 'BR01ST4444',
            'category' => 'AUTO',
            'district_id' => 10,
        ])->json('id');
        $this->actingAs($owner)
            ->patchJson("/api/v1/fleet/vehicles/{$vehicleId}", ['status' => 'on_trip'])
            ->assertStatus(422);

        $driverId = $this->actingAs($owner)->postJson('/api/v1/fleet/drivers', [
            'name' => 'Kyc Driver',
            'email' => 'kyc-driver@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->json('id');
        $this->actingAs($owner)
            ->patchJson("/api/v1/fleet/drivers/{$driverId}/kyc", ['kyc_status' => 'verified'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->patchJson("/api/v1/fleet/drivers/{$driverId}/kyc", ['kyc_status' => 'under_review'])
            ->assertOk()
            ->assertJsonPath('kycStatus', 'under_review');
    }

    public function test_documents_status_reports_and_isolation(): void
    {
        $owner = $this->owner(1);
        $other = $this->owner(2);
        $vehicleId = $this->actingAs($owner)->postJson('/api/v1/fleet/vehicles', [
            'registration_no' => 'BR01RP5555',
            'category' => 'SUV',
            'district_id' => 10,
        ])->json('id');
        $driverId = $this->actingAs($owner)->postJson('/api/v1/fleet/drivers', [
            'name' => 'Suman',
            'email' => 'suman@karnacab.local',
            'password' => 'ChangeMe@123',
            'license_no' => 'BR-LIC-9',
        ])->json('id');

        $this->actingAs($owner)->postJson("/api/v1/fleet/vehicles/{$vehicleId}/documents", [
            'type' => 'RC',
            'storage_key' => 'rc/1',
            'expires_at' => now()->addDays(10)->toDateString(),
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/fleet/vehicles/{$vehicleId}/documents", [
            'type' => 'INSURANCE',
            'storage_key' => 'ins/1',
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/fleet/vehicles/{$vehicleId}/documents", [
            'type' => 'PHOTO',
            'storage_key' => 'photo/1',
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/fleet/drivers/{$driverId}/documents", [
            'type' => 'LICENSE',
            'storage_key' => 'lic/1',
            'expires_at' => now()->addYear()->toDateString(),
        ])->assertOk();
        $this->actingAs($owner)->postJson("/api/v1/fleet/drivers/{$driverId}/assign", ['vehicle_id' => $vehicleId])->assertOk();
        $this->actingAs($owner)->patchJson("/api/v1/fleet/drivers/{$driverId}/status", [
            'account_status' => 'ACTIVE',
            'duty_status' => 'online',
        ])->assertOk()->assertJsonPath('online', true);

        $this->insertCompletedTrip(1, $vehicleId, $driverId, 'KCFLT1', 80000);
        $this->insertCompletedTrip(2, null, null, 'KCFLT2', 90000);

        $this->actingAs($owner)
            ->getJson('/api/v1/fleet/reports')
            ->assertOk()
            ->assertJsonPath('trips.total', 1)
            ->assertJsonPath('revenue.totalRupees', 800)
            ->assertJsonFragment(['publicRef' => 'KCFLT1'])
            ->assertJsonMissing(['publicRef' => 'KCFLT2']);

        $this->actingAs($other)
            ->getJson('/api/v1/fleet/reports')
            ->assertOk()
            ->assertJsonMissing(['publicRef' => 'KCFLT1']);

        $this->actingAs($owner)->get('/fleet/reports')->assertOk()->assertSee('Fleet reports')->assertSee('BR01RP5555');
        $this->actingAs($owner)->deleteJson("/api/v1/fleet/drivers/{$driverId}")->assertOk();
        $this->assertNull(DB::connection('platform')->table('drivers')->where('id', $driverId)->value('fleet_owner_id'));
    }

    public function test_admin_cannot_use_fleet_owner_workspace(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->getJson('/api/v1/fleet')->assertForbidden();
    }

    private function owner(int $fleetId): User
    {
        return User::factory()->create([
            'role' => OperatorRole::FLEET_OWNER,
            'fleet_owner_id' => $fleetId,
            'district_id' => $fleetId === 1 ? 10 : 20,
            'state_id' => $fleetId === 1 ? 1 : 2,
        ]);
    }

    private function insertCompletedTrip(int $fleetId, ?int $vehicleId, ?int $driverId, string $ref, int $paise): void
    {
        $customer = DB::connection('platform')->table('users')->insertGetId([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => $ref,
            'email' => strtolower($ref).'@karnacab.local',
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $payload = [
            'public_ref' => $ref,
            'customer_id' => $customer,
            'product' => 'ONE_WAY',
            'status' => 'COMPLETED',
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'district_id' => $fleetId === 1 ? 10 : 20,
            'quote_paise' => $paise,
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($vehicleId) {
            $payload['vehicle_id'] = $vehicleId;
        }
        if ($driverId) {
            $payload['driver_id'] = $driverId;
        }
        DB::connection('platform')->table('bookings')->insert($payload);
    }

    private function seedFleets(): void
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
        $u1 = $db->table('users')->insertGetId([
            'role' => 'FLEET_OWNER',
            'status' => 'ACTIVE',
            'name' => 'Patna Owner',
            'email' => 'patna-fleet@karnacab.local',
            'password_hash' => 'x',
            'district_id' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $u2 = $db->table('users')->insertGetId([
            'role' => 'FLEET_OWNER',
            'status' => 'ACTIVE',
            'name' => 'Ranchi Owner',
            'email' => 'ranchi-fleet@karnacab.local',
            'password_hash' => 'x',
            'district_id' => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $db->table('fleet_owners')->insert([
            ['id' => 1, 'user_id' => $u1, 'trade_name' => 'Patna Cabs'],
            ['id' => 2, 'user_id' => $u2, 'trade_name' => 'Ranchi Motors'],
        ]);
    }
}
