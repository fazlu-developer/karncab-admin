<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class AdvertisingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        $this->seedAds();
    }

    public function test_advertiser_login_opens_ads_workspace(): void
    {
        $ads = User::factory()->create([
            'email' => 'ads-login@karnacab.local',
            'password' => 'ChangeMe@123',
            'role' => OperatorRole::ADVERTISER,
            'nest_user_id' => 900,
        ]);

        $this->post('/login', [
            'email' => 'ads-login@karnacab.local',
            'password' => 'ChangeMe@123',
        ])->assertRedirect('/ads');

        $this->actingAs($ads)
            ->get('/ads')
            ->assertOk()
            ->assertSee('Submit campaign')
            ->assertSee('Hotel Patna');
        $this->actingAs($ads)->get('/dashboard')->assertRedirect(route('ads.index'));
        $this->actingAs($ads)->get('/ops/advertising')->assertRedirect(route('ads.index'));
    }

    public function test_advertiser_submit_stays_pending_and_cannot_self_publish(): void
    {
        $ads = $this->advertiser();
        $created = $this->actingAs($ads)->postJson('/api/v1/ads', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->json();

        $this->actingAs($ads)->postJson('/api/v1/ads', $this->payload() + ['status' => 'published'])
            ->assertStatus(422);

        $this->actingAs($ads)->postJson('/api/v1/ads/'.$created['id'].'/review', ['status' => 'approved'])
            ->assertForbidden();
        $this->assertSame('pending', DB::connection('platform')->table('ad_campaigns')->where('id', $created['id'])->value('status'));
    }

    public function test_admin_can_approve_reject_pause_resume_and_set_targeting(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::ADMIN]);
        $ads = $this->advertiser();
        $id = $this->actingAs($ads)->postJson('/api/v1/ads', $this->payload())->assertCreated()->json('id');

        $this->actingAs($admin)->postJson('/api/v1/ads/'.$id.'/review', ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('status', 'published');

        $this->actingAs($admin)->patchJson('/api/v1/ads/'.$id, [
            'state_id' => 1,
            'district_id' => 10,
            'target_city' => 'Patna',
        ])->assertOk()->assertJsonPath('targetCity', 'Patna');

        $this->actingAs($admin)->postJson('/api/v1/ads/'.$id.'/pause')->assertOk()->assertJsonPath('status', 'paused');
        $this->actingAs($admin)->postJson('/api/v1/ads/'.$id.'/resume')->assertOk()->assertJsonPath('status', 'published');

        $this->actingAs($admin)->postJson('/api/v1/ads', $this->payload(['title' => 'Reject me']))->assertCreated();
        $rejectId = (int) DB::connection('platform')->table('ad_campaigns')->where('title', 'Reject me')->value('id');
        $this->actingAs($admin)->postJson('/api/v1/ads/'.$rejectId.'/review', [
            'status' => 'rejected',
            'reason' => 'Off policy',
        ])->assertOk()->assertJsonPath('status', 'rejected');

        $this->actingAs($admin)->get('/ads')->assertOk()->assertSee('Impressions');
    }

    public function test_serve_blocks_critical_screens_and_client_location(): void
    {
        $rider = $this->rider();
        $this->actingAs($rider)->getJson('/api/v1/ads/serve?placement=home')
            ->assertOk()
            ->assertJsonPath('suppressed', null)
            ->assertJsonFragment(['campaign' => 'Hotel Patna']);

        foreach (['trip', 'sos', 'payment', 'otp', 'navigation'] as $placement) {
            $this->actingAs($rider)->getJson('/api/v1/ads/serve?placement='.$placement)
                ->assertOk()
                ->assertJsonPath('ads', [])
                ->assertJsonPath('suppressed', 'blocked_placement');
        }

        $this->actingAs($rider)->getJson('/api/v1/ads/serve?placement=home&districtId=10')
            ->assertStatus(422);
        $this->actingAs($rider)->getJson('/api/v1/ads/serve?placement=home&city=Patna')
            ->assertStatus(422);
    }

    public function test_live_ride_suppresses_home_ads(): void
    {
        $rider = $this->rider();
        DB::connection('platform')->table('bookings')->insert([
            'public_ref' => 'KCLIVE',
            'customer_id' => 800,
            'status' => 'ONGOING',
            'pickup_text' => 'A',
            'drop_text' => 'B',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($rider)->getJson('/api/v1/ads/serve?placement=home')
            ->assertOk()
            ->assertJsonPath('ads', [])
            ->assertJsonPath('suppressed', 'active_trip');
    }

    public function test_impressions_and_clicks_count_as_revenue(): void
    {
        $rider = $this->rider();
        $this->actingAs($rider)->postJson('/api/v1/ads/1/impression', ['placement' => 'home'])
            ->assertOk()
            ->assertJsonPath('impressions', 1)
            ->assertJsonPath('revenuePaise', 10);
        $this->actingAs($rider)->postJson('/api/v1/ads/1/click', ['placement' => 'home'])
            ->assertOk()
            ->assertJsonPath('clicks', 1)
            ->assertJsonPath('revenuePaise', 110);

        $this->actingAs($rider)->postJson('/api/v1/ads/1/impression', ['placement' => 'trip'])
            ->assertForbidden();
    }

    public function test_other_advertiser_is_hidden_and_fleet_is_forbidden(): void
    {
        $other = User::factory()->create([
            'role' => OperatorRole::ADVERTISER,
            'nest_user_id' => 901,
        ]);
        $this->actingAs($other)->getJson('/api/v1/ads/1')->assertNotFound();
        $this->actingAs($other)->get('/ads')->assertOk()->assertDontSee('Hotel Patna');

        $fleet = User::factory()->create(['role' => OperatorRole::FLEET_OWNER, 'fleet_owner_id' => 1]);
        $this->actingAs($fleet)->get('/ads')->assertForbidden();
        $this->actingAs($fleet)->getJson('/api/v1/ads')->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Spring stay',
            'business_name' => 'Hotel Ganga',
            'business_info' => 'Rooms near Gandhi Maidan',
            'category' => 'hotels',
            'campaign_type' => 'banner',
            'state_id' => 1,
            'district_id' => 10,
            'target_city' => 'Patna',
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonth()->toDateString(),
            'budget_rupees' => 500,
        ], $overrides);
    }

    private function advertiser(): User
    {
        return User::factory()->create([
            'role' => OperatorRole::ADVERTISER,
            'nest_user_id' => 900,
        ]);
    }

    private function rider(): User
    {
        return User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 800,
            'state_id' => 1,
            'district_id' => 10,
        ]);
    }

    private function seedAds(): void
    {
        $db = DB::connection('platform');
        $db->table('states')->insert([['id' => 1, 'name' => 'Bihar']]);
        $db->table('districts')->insert([['id' => 10, 'state_id' => 1, 'name' => 'Patna']]);
        foreach ([
            [
                'id' => 800,
                'role' => 'CUSTOMER',
                'status' => 'ACTIVE',
                'name' => 'Rider',
                'email' => 'rider-ads@karnacab.local',
                'password_hash' => 'x',
                'last_address' => 'Patna Junction, Patna',
                'state_id' => 1,
                'district_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 900,
                'role' => 'ADVERTISER',
                'status' => 'ACTIVE',
                'name' => 'Hotel Ads',
                'email' => 'hotel-ads@karnacab.local',
                'password_hash' => 'x',
                'last_address' => null,
                'state_id' => 1,
                'district_id' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 901,
                'role' => 'ADVERTISER',
                'status' => 'ACTIVE',
                'name' => 'Other Ads',
                'email' => 'other-ads@karnacab.local',
                'password_hash' => 'x',
                'last_address' => null,
                'state_id' => null,
                'district_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ] as $user) {
            $db->table('users')->insert($user);
        }
        $db->table('system_settings')->insert([
            ['key' => 'ad_impression_paise', 'value' => '10'],
            ['key' => 'ad_click_paise', 'value' => '100'],
        ]);
        $db->table('ad_campaigns')->insert([
            'id' => 1,
            'advertiser_user_id' => 900,
            'title' => 'Hotel Patna',
            'business_name' => 'Hotel Patna',
            'business_info' => 'Stay near the station',
            'category' => 'hotels',
            'campaign_type' => 'banner',
            'target_city' => 'Patna',
            'state_id' => 1,
            'district_id' => 10,
            'starts_on' => now()->subDay(),
            'ends_on' => now()->addMonth(),
            'budget_paise' => 50_000,
            'budget_used_paise' => 0,
            'status' => 'published',
            'impressions' => 0,
            'clicks' => 0,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
