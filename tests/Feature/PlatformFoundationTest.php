<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_operator_becomes_admin(): void
    {
        $this->post('/register', [
            'name' => 'Ops Admin',
            'email' => 'ops@karnacab.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', [
            'email' => 'ops@karnacab.local',
            'role' => OperatorRole::ADMIN,
        ]);
    }

    public function test_later_operators_are_pending_without_domain_write(): void
    {
        User::factory()->create([
            'role' => OperatorRole::ADMIN,
            'email' => 'admin@karnacab.local',
        ]);

        $this->post('/register', [
            'name' => 'Pending Head',
            'email' => 'head@karnacab.local',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'head@karnacab.local')->first();
        $this->assertSame(OperatorRole::PENDING, $user->role);
        $this->assertFalse($user->can('bookings.read'));
        $this->assertFalse($user->can('franchise.write'));
    }

    public function test_district_head_can_read_bookings_and_write_franchise(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::DISTRICT_HEAD]);

        $this->assertTrue($user->can('bookings.read'));
        $this->assertTrue($user->can('franchise.write'));
        $this->assertFalse($user->can('advertising.write'));
    }

    public function test_district_head_cannot_access_another_district(): void
    {
        $this->assertFalse(
            \App\Platform\TerritoryScope::canAccessDistrict(10, OperatorRole::DISTRICT_HEAD, 99),
        );
        $this->assertTrue(
            \App\Platform\TerritoryScope::canAccessDistrict(10, OperatorRole::DISTRICT_HEAD, 10),
        );
        $this->assertFalse(
            \App\Platform\TerritoryScope::canAccessState(1, OperatorRole::STATE_HEAD, 2),
        );
        $this->assertTrue(
            \App\Platform\TerritoryScope::canAccessState(1, OperatorRole::STATE_HEAD, 1),
        );
        $this->assertTrue(
            \App\Platform\TerritoryScope::canAccessDistrict(null, OperatorRole::ADMIN, 99),
        );
    }

    public function test_authenticated_session_endpoint(): void
    {
        $user = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($user)
            ->get('/api/v1/platform/session')
            ->assertOk()
            ->assertJsonPath('data.operator.role', OperatorRole::ADMIN)
            ->assertJsonPath('data.apiVersion', '1');
    }
}
