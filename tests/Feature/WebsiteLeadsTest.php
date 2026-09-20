<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class WebsiteLeadsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        DB::connection('platform')->table('leads')->insert([
            'type' => 'SUPPORT',
            'status' => 'NEW',
            'name' => 'Rakesh',
            'phone' => '9876543210',
            'email' => 'rakesh@example.com',
            'district' => 'Patna',
            'message' => 'Need help with a website enquiry.',
            'created_at' => now(),
        ]);
    }

    public function test_admin_can_list_and_close_website_leads(): void
    {
        $admin = User::factory()->create(['role' => OperatorRole::SUPER_ADMIN]);

        $this->actingAs($admin)
            ->get('/leads')
            ->assertOk()
            ->assertSee('Website enquiries')
            ->assertSee('Rakesh')
            ->assertSee('9876543210');

        $this->actingAs($admin)->get('/ops/leads')->assertRedirect(route('leads.index'));

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Website leads');

        $id = (int) DB::connection('platform')->table('leads')->value('id');
        $this->actingAs($admin)
            ->patch("/leads/{$id}", ['status' => 'CLOSED'])
            ->assertRedirect();

        $this->assertSame('CLOSED', DB::connection('platform')->table('leads')->where('id', $id)->value('status'));
    }

    public function test_advertiser_cannot_open_leads(): void
    {
        $ads = User::factory()->create(['role' => OperatorRole::ADVERTISER]);
        $this->actingAs($ads)->get('/leads')->assertForbidden();
    }
}
