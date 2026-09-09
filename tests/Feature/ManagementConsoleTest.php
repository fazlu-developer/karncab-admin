<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class ManagementConsoleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
    }

    public function test_super_admin_dashboard_reads_platform_tables(): void
    {
        DB::connection('platform')->table('users')->insert([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Fazlu',
            'email' => 'customer@karnacab.local',
            'phone' => '9999999999',
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Total users')
            ->assertSee('Pending KYC')
            ->assertSee('Users')
            ->assertSee('Live Map')
            ->assertSee('Audit Logs')
            ->assertSee('Fazlu');
    }

    public function test_pending_operator_cannot_open_user_management(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::PENDING]);

        $this->actingAs($user)
            ->get('/ops/users')
            ->assertForbidden();
    }

    public function test_booking_filters_are_available_to_admin(): void
    {
        $customerId = DB::connection('platform')->table('users')->insertGetId([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Fazlu',
            'email' => 'customer@karnacab.local',
            'phone' => '9999999999',
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('bookings')->insert([
            'public_ref' => 'KCDEMO0001',
            'customer_id' => $customerId,
            'product' => 'ONE_WAY',
            'status' => 'COMPLETED',
            'pickup_text' => 'Patna',
            'drop_text' => 'Gaya',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($user)
            ->get('/ops/bookings?publicRef=KCDEMO0001')
            ->assertOk()
            ->assertSee('Booking ID')
            ->assertSee('KCDEMO0001')
            ->assertSee('Lifecycle');
    }

    public function test_login_does_not_call_nest(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@karnacab.local',
            'password' => 'ChangeMe@123',
            'role' => OperatorRole::ADMIN,
        ]);

        $this->post('/login', [
            'email' => 'admin@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_page_is_available(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('Send reset link');
    }

    public function test_admin_can_search_and_create_platform_users(): void
    {
        DB::connection('platform')->table('users')->insert([
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Fazlu',
            'email' => 'customer@karnacab.local',
            'phone' => '9999999999',
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($admin)
            ->get('/users?q=Fazlu')
            ->assertOk()
            ->assertSee('Fazlu')
            ->assertSee('customer@karnacab.local');

        $this->actingAs($admin)
            ->post('/users', [
                'name' => 'Riya',
                'email' => 'riya@karnacab.local',
                'phone' => '9111111111',
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'password' => 'ChangeMe@123',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'riya@karnacab.local',
            'name' => 'Riya',
        ], 'platform');
    }

    public function test_admin_can_manage_drivers(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($admin)
            ->post('/drivers', [
                'name' => 'Amit',
                'email' => 'amit.driver@karnacab.local',
                'phone' => '9888888888',
                'status' => 'ACTIVE',
                'license_no' => 'BR-01-2026',
                'kyc_status' => 'pending',
                'password' => 'ChangeMe@123',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->get('/drivers?q=Amit')
            ->assertOk()
            ->assertSee('Amit')
            ->assertSee('BR-01-2026');
    }

    public function test_api_v1_dashboard_uses_the_same_laravel_controller(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($user)
            ->getJson('/api/v1/ops/dashboard')
            ->assertOk()
            ->assertJsonPath('kpis.totalUsers', 0)
            ->assertJsonStructure(['kpis' => ['totalDrivers', 'activeRides', 'pendingKyc']]);
    }
}
