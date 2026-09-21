<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class AdminWorkspaceModulesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
    }

    public function test_admin_can_manage_states_branding_services_and_settings(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Website & branding')
            ->assertSee('Service Management')
            ->assertSee('Manual Booking')
            ->assertDontSee('Driver Leave');

        $this->actingAs($admin)
            ->post('/organization/states', ['name' => 'Bihar', 'code' => 'BR', 'status' => 'ACTIVE'])
            ->assertRedirect();
        $this->assertDatabaseHas('states', ['name' => 'Bihar'], 'platform');

        $stateId = (int) DB::connection('platform')->table('states')->value('id');
        $this->actingAs($admin)
            ->post('/organization/districts', ['state_id' => $stateId, 'name' => 'Patna', 'code' => 'PAT', 'status' => 'ACTIVE'])
            ->assertRedirect();

        $this->actingAs($admin)->get('/ops/branding')->assertOk()->assertSee('SEO title');
        $this->actingAs($admin)->post('/ops/branding', [
            'name' => 'KarnaCab',
            'tagline' => 'Rides in Bihar',
            'defaultSeoTitle' => 'KarnaCab',
            'contactEmail' => 'ops@karnacab.in',
        ])->assertRedirect();

        $this->actingAs($admin)->get('/ops/services')->assertOk()->assertSee('Sedan');
        $this->actingAs($admin)->get('/ops/settings')->assertOk()->assertSee('Driver search radius');
        $this->actingAs($admin)->post('/ops/settings', [
            'driver_search_radius_km' => 15,
            'ride_request_timeout_seconds' => 45,
            'driver_wallet_min_rupees' => 0,
            'driver_wallet_min_fare_percent' => 0,
        ])->assertRedirect();
        $this->actingAs($admin)->get('/ops/audit')->assertOk();
        $this->actingAs($admin)->get('/ops/travel')->assertOk();
        $this->actingAs($admin)->get('/ops/parcels')->assertOk();
        $this->actingAs($admin)->get('/ops/manual-bookings')->assertOk();
        $this->actingAs($admin)->get('/ops/roles')->assertOk();
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('Event templates');
    }
}
