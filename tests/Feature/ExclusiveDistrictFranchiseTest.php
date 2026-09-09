<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class ExclusiveDistrictFranchiseTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedDistricts();
    }

    public function test_application_does_not_claim_the_exclusive_seat(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/v1/franchises', $this->applicationPayload('Alpha Exclusive', 10))
            ->assertCreated()
            ->assertJsonPath('status', 'APPLIED')
            ->assertJsonPath('exclusiveSeat', false)
            ->assertJsonPath('activeDistrictKey', null);

        $this->assertNull(DB::connection('platform')->table('franchises')->value('active_district_key'));
    }

    public function test_second_active_franchise_in_same_district_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $first = $this->applyAndActivate($admin, 'Seat A', 10);
        $second = $this->apply($admin, 'Seat B', 10);

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$second}/lifecycle", ['status' => 'UNDER_REVIEW'])
            ->assertOk();
        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$second}/lifecycle", ['status' => 'APPROVED'])
            ->assertOk();

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$second}/lifecycle", ['status' => 'ACTIVE'])
            ->assertStatus(409)
            ->assertSee('already has an active District Head', false);

        $this->assertSame('10', DB::connection('platform')->table('franchises')->where('id', $first)->value('active_district_key'));
        $this->assertNull(DB::connection('platform')->table('franchises')->where('id', $second)->value('active_district_key'));
    }

    public function test_database_unique_index_blocks_two_active_keys(): void
    {
        $ownerA = $this->platformUser('owner-a@karnacab.local');
        $ownerB = $this->platformUser('owner-b@karnacab.local');
        $this->insertFranchise($ownerA, 10, 'ACTIVE', '10');

        $this->expectException(QueryException::class);
        $this->insertFranchise($ownerB, 10, 'ACTIVE', '10');
    }

    public function test_district_head_and_exclusive_franchise_cannot_both_be_active(): void
    {
        $head = User::factory()->create([
            'role' => OperatorRole::STATE_HEAD,
            'state_id' => 1,
        ]);

        $created = $this->actingAs($head)
            ->postJson('/api/v1/state/district-heads', [
                'name' => 'Patna Head',
                'email' => 'patna-head@karnacab.local',
                'district_id' => 10,
                'password' => 'ChangeMe@123',
            ])
            ->assertCreated()
            ->json();

        $this->assertSame('APPLIED', $created['status']);
        $this->activateFromApplied($head, (int) $created['id']);

        $franchiseId = $this->apply($head, 'Patna Franchise', 10);
        $this->actingAs($head)->patchJson("/api/v1/franchises/{$franchiseId}/lifecycle", ['status' => 'UNDER_REVIEW'])->assertOk();
        $this->actingAs($head)->patchJson("/api/v1/franchises/{$franchiseId}/lifecycle", ['status' => 'APPROVED'])->assertOk();
        $this->actingAs($head)
            ->patchJson("/api/v1/franchises/{$franchiseId}/lifecycle", ['status' => 'ACTIVE'])
            ->assertStatus(409);
    }

    public function test_reassignment_is_blocked_when_target_district_has_an_active_seat(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $this->applyAndActivate($admin, 'Patna Seat', 10);
        $moving = $this->applyAndActivate($admin, 'Gaya Seat', 11);

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$moving}/territory", ['district_id' => 10])
            ->assertStatus(409);
    }

    public function test_termination_releases_the_seat_for_a_new_active_franchise(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $first = $this->applyAndActivate($admin, 'Outgoing', 10);

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$first}/lifecycle", [
                'status' => 'TERMINATED',
                'reason' => 'Contract ended',
            ])
            ->assertOk()
            ->assertJsonPath('exclusiveSeat', false)
            ->assertJsonPath('activeDistrictKey', null);

        $second = $this->apply($admin, 'Incoming', 10);
        $this->activateFromApplied($admin, $second);

        $this->assertSame('10', DB::connection('platform')->table('franchises')->where('id', $second)->value('active_district_key'));
        $this->assertNull(DB::connection('platform')->table('franchises')->where('id', $first)->value('active_district_key'));
    }

    public function test_kyc_agreement_fees_wallet_commission_renewal_and_performance_controls(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $id = $this->applyAndActivate($admin, 'Ops Franchise', 10);

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$id}/kyc", ['kyc_status' => 'verified'])
            ->assertOk()
            ->assertJsonPath('kycStatus', 'verified');

        $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$id}/documents", [
                'type' => 'pan',
                'storage_key' => 'kyc/pan-1',
                'original_name' => 'pan.pdf',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$id}/agreement", ['version' => 'v1'])
            ->assertOk()
            ->assertJsonPath('agreementStatus', 'signed');

        $fees = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$id}/fees", [
                'kind' => 'onboarding',
                'amount_paise' => 2500000,
            ])
            ->assertOk()
            ->json('fees');
        $feeId = (int) (is_array($fees[0]) ? $fees[0]['id'] : $fees[0]->id);

        $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$id}/fees/{$feeId}/pay")
            ->assertOk();

        $payload = $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$id}/commission", ['commission_percent' => 8.5])
            ->assertOk()
            ->json();
        $this->assertEqualsWithDelta(8.5, (float) $payload['commissionPercent'], 0.001);

        $renewals = $this->actingAs($admin)
            ->postJson("/api/v1/franchises/{$id}/renewals", [
                'period_start' => '2026-10-01',
                'period_end' => '2027-09-30',
            ])
            ->assertOk()
            ->json('renewals');
        $renewalId = (int) (is_array($renewals[0]) ? $renewals[0]['id'] : $renewals[0]->id);

        $this->actingAs($admin)
            ->patchJson("/api/v1/franchises/{$id}/renewals/{$renewalId}", ['status' => 'approved'])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson("/api/v1/franchises/{$id}")
            ->assertOk()
            ->assertJsonPath('wallet.ownerType', 'FRANCHISE')
            ->assertJsonPath('performance.completedTrips', 0);

        $this->actingAs($admin)
            ->get('/franchises/'.$id)
            ->assertOk()
            ->assertSee('Exclusive seat')
            ->assertSee('Lifecycle');
    }

    public function test_frontend_cannot_force_active_on_apply(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);

        $this->actingAs($admin)
            ->postJson('/api/v1/franchises', $this->applicationPayload('Forced', 10) + ['status' => 'ACTIVE'])
            ->assertCreated()
            ->assertJsonPath('status', 'APPLIED');
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationPayload(string $trade, int $districtId): array
    {
        return [
            'kind' => 'EXCLUSIVE_FRANCHISE',
            'trade_name' => $trade,
            'district_id' => $districtId,
            'name' => $trade.' Owner',
            'email' => strtolower(str_replace(' ', '.', $trade)).'@karnacab.local',
            'password' => 'ChangeMe@123',
        ];
    }

    private function apply(User $operator, string $trade, int $districtId): int
    {
        return (int) $this->actingAs($operator)
            ->postJson('/api/v1/franchises', $this->applicationPayload($trade, $districtId))
            ->assertCreated()
            ->json('id');
    }

    private function applyAndActivate(User $operator, string $trade, int $districtId): int
    {
        $id = $this->apply($operator, $trade, $districtId);
        $this->activateFromApplied($operator, $id);

        return $id;
    }

    private function activateFromApplied(User $operator, int $id): void
    {
        foreach (['UNDER_REVIEW', 'APPROVED', 'ACTIVE'] as $status) {
            $this->actingAs($operator)
                ->patchJson("/api/v1/franchises/{$id}/lifecycle", ['status' => $status])
                ->assertOk()
                ->assertJsonPath('status', $status);
        }
    }

    private function platformUser(string $email): int
    {
        return (int) DB::connection('platform')->table('users')->insertGetId([
            'role' => 'FRANCHISE',
            'status' => 'ACTIVE',
            'name' => $email,
            'email' => $email,
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertFranchise(int $ownerId, int $districtId, string $status, ?string $key): int
    {
        return (int) DB::connection('platform')->table('franchises')->insertGetId([
            'district_id' => $districtId,
            'state_id' => 1,
            'owner_user_id' => $ownerId,
            'kind' => 'EXCLUSIVE_FRANCHISE',
            'status' => $status,
            'active_district_key' => $key,
            'trade_name' => 'Raw '.$ownerId,
            'kyc_status' => 'pending',
            'agreement_status' => 'unsigned',
            'fee_amount_paise' => 0,
            'commission_percent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedDistricts(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([
            ['id' => 1, 'name' => 'Bihar'],
            ['id' => 2, 'name' => 'Jharkhand'],
        ]);
        $db->table('districts')->insert([
            ['id' => 10, 'state_id' => 1, 'name' => 'Patna'],
            ['id' => 11, 'state_id' => 1, 'name' => 'Gaya'],
            ['id' => 20, 'state_id' => 2, 'name' => 'Ranchi'],
        ]);
    }
}
