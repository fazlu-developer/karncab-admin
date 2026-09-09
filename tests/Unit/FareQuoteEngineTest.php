<?php

namespace Tests\Unit;

use App\Platform\FareQuoteEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class FareQuoteEngineTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        DB::connection('platform')->table('fare_rules')->insert([
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'min_km' => 2,
            'included_km' => 2,
            'per_km_paise' => 1200,
            'extra_km_paise' => 1500,
            'waiting_paise_per_min' => 100,
            'night_percent' => 25,
            'gst_percent' => 5,
            'cancel_paise' => 0,
            'discount_paise' => 0,
            'discount_percent' => 0,
            'driver_allow_paise' => 0,
            'apply_toll' => true,
            'apply_parking' => true,
            'apply_gst_to_base' => true,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_quote_uses_fare_rules_not_client_totals(): void
    {
        $quote = app(FareQuoteEngine::class)->quote([
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'distanceKm' => 10,
        ]);

        $this->assertSame('server', $quote['source']);
        $this->assertSame(10.0, $quote['billedKm']);
        $this->assertSame(8.0, $quote['extraKm']);
        $this->assertSame(12000, $quote['breakdown']['extraPaise']);
        $this->assertGreaterThan(0, $quote['totalPaise']);
        $this->assertSame($quote['totalPaise'] / 100, $quote['totalRupees']);
    }
}
