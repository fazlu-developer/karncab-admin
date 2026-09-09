<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class ReportingAnalyticsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedReports();
    }

    public function test_admin_opens_reports_and_exports_csv_excel_pdf(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->actingAs($admin)->get('/ops/reports')->assertRedirect(route('reports.index'));
        $this->actingAs($admin)->get('/reports')
            ->assertOk()
            ->assertSee('Daily bookings')
            ->assertSee('Gross revenue')
            ->assertSee('Parcel orders')
            ->assertSee('Excel')
            ->assertSee('PDF');

        $json = $this->actingAs($admin)->getJson('/api/v1/reports/daily_bookings')->assertOk()->json();
        $this->assertNotEmpty($json['rows']);
        $completedRefs = array_column($this->actingAs($admin)->getJson('/api/v1/reports/completed_rides')->json('rows'), 'publicRef');
        $this->assertContains('KCMINE', $completedRefs);
        $this->assertContains('KCOTHER', $completedRefs);

        $csv = $this->actingAs($admin)->get('/reports/completed_rides/export?format=csv')->assertOk()->getContent();
        $this->assertStringContainsString('KCMINE', $csv);
        $this->assertStringContainsString('KCOTHER', $csv);

        $xlsx = $this->actingAs($admin)->get('/api/v1/reports/completed_rides/export?format=xlsx')->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $xlsx->headers->get('content-type'));
        $this->assertNotEmpty($xlsx->getContent());

        $pdf = $this->actingAs($admin)->get('/reports/gross_revenue/export?format=pdf')->assertOk();
        $pdf->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_district_head_cannot_see_or_export_another_district(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::DISTRICT_HEAD,
            'district_id' => 10,
            'state_id' => 1,
        ]);

        $this->actingAs($head)->get('/reports')->assertOk()->assertDontSee('State performance')->assertDontSee('Platform commission');
        $completed = $this->actingAs($head)->getJson('/api/v1/reports/completed_rides?districtId=20')->assertOk()->json('rows');
        $refs = array_column($completed, 'publicRef');
        $this->assertContains('KCMINE', $refs);
        $this->assertNotContains('KCOTHER', $refs);

        $csv = $this->actingAs($head)->get('/api/v1/reports/completed_rides/export?format=csv&districtId=20')->assertOk()->getContent();
        $this->assertStringContainsString('KCMINE', $csv);
        $this->assertStringNotContainsString('KCOTHER', $csv);

        $this->actingAs($head)->getJson('/api/v1/reports/platform_commission')->assertForbidden();
    }

    public function test_advertiser_cannot_open_reports(): void
    {
        $advertiser = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($advertiser)->get('/reports')->assertForbidden();
        $this->actingAs($advertiser)->getJson('/api/v1/reports')->assertForbidden();
    }

    public function test_fleet_owner_reports_only_own_trips(): void
    {
        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $refs = array_column($this->actingAs($fleet)->getJson('/api/v1/reports/completed_rides')->assertOk()->json('rows'), 'publicRef');
        $this->assertContains('KCFLEET', $refs);
        $this->assertNotContains('KCMINE', $refs);
        $this->assertNotContains('KCOTHER', $refs);
    }

    private function seedReports(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar'], ['id' => 2, 'name' => 'Jharkhand']]);
        $db->table('districts')->insert([
            ['id' => 10, 'state_id' => 1, 'name' => 'Patna'],
            ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi'],
        ]);
        $mine = $db->table('users')->insertGetId([
            'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Patna Rider',
            'email' => 'patna-r@karnacab.local', 'password_hash' => 'x', 'district_id' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = $db->table('users')->insertGetId([
            'role' => 'CUSTOMER', 'status' => 'ACTIVE', 'name' => 'Ranchi Rider',
            'email' => 'ranchi-r@karnacab.local', 'password_hash' => 'x', 'district_id' => 20,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $fleetUser = $db->table('users')->insertGetId([
            'role' => 'FLEET_OWNER', 'status' => 'ACTIVE', 'name' => 'Fleet',
            'email' => 'fleet-r@karnacab.local', 'password_hash' => 'x', 'district_id' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('fleet_owners')->insert(['id' => 1, 'user_id' => $fleetUser, 'trade_name' => 'Patna Fleet']);
        $driverUser = $db->table('users')->insertGetId([
            'role' => 'DRIVER', 'status' => 'ACTIVE', 'name' => 'Fleet Driver',
            'email' => 'fd@karnacab.local', 'password_hash' => 'x', 'district_id' => 10,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $driverId = $db->table('drivers')->insertGetId([
            'user_id' => $driverUser, 'fleet_owner_id' => 1, 'online' => false,
            'kyc_status' => 'verified', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $vehicleId = $db->table('vehicles')->insertGetId([
            'registration_no' => 'BR01RP1111', 'category' => 'SEDAN', 'status' => 'active',
            'fleet_owner_id' => 1, 'district_id' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('bookings')->insert([
            'public_ref' => 'KCMINE', 'customer_id' => $mine, 'product' => 'ONE_WAY', 'status' => 'COMPLETED',
            'pickup_text' => 'Patna', 'drop_text' => 'Gaya', 'district_id' => 10, 'quote_paise' => 50000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('bookings')->insert([
            'public_ref' => 'KCOTHER', 'customer_id' => $other, 'product' => 'ONE_WAY', 'status' => 'COMPLETED',
            'pickup_text' => 'Ranchi', 'drop_text' => 'Bokaro', 'district_id' => 20, 'quote_paise' => 80000,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('bookings')->insert([
            'public_ref' => 'KCFLEET', 'customer_id' => $mine, 'driver_id' => $driverId, 'vehicle_id' => $vehicleId,
            'product' => 'LOCAL_CAB', 'status' => 'COMPLETED', 'pickup_text' => 'A', 'drop_text' => 'B',
            'district_id' => 10, 'quote_paise' => 25000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('parcel_shipments')->insert([
            'public_ref' => 'PRMINE', 'status' => 'DELIVERED', 'customer_id' => $mine, 'district_id' => 10,
            'quote_paise' => 12000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $db->table('payments')->insert([
            'public_ref' => 'PAYMINE', 'method' => 'upi', 'kind' => 'payment', 'status' => 'success',
            'amount_paise' => 50000, 'customer_id' => $mine, 'booking_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
