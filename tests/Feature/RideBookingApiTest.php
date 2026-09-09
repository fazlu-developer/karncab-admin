<?php

namespace Tests\Feature;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesPlatformSchema;
use Tests\TestCase;

class RideBookingApiTest extends TestCase
{
    use RefreshDatabase;
    use CreatesPlatformSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpPlatformSchema();
        DB::connection('platform')->table('users')->insert([
            'id' => 910,
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'name' => 'Web Rider',
            'email' => 'webrider@karnacab.local',
            'password_hash' => 'x',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('platform')->table('fare_rules')->insert([
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'min_km' => 2,
            'included_km' => 2,
            'per_km_paise' => 1200,
            'extra_km_paise' => 1500,
            'waiting_paise_per_min' => 0,
            'night_percent' => 0,
            'gst_percent' => 5,
            'cancel_paise' => 0,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status' => 'OK',
                'predictions' => [[
                    'place_id' => 'ChIJpickup',
                    'description' => 'Patna Junction, Bihar',
                    'structured_formatting' => ['main_text' => 'Patna Junction', 'secondary_text' => 'Bihar'],
                ]],
                'result' => [
                    'name' => 'Patna Junction',
                    'formatted_address' => 'Patna Junction, Bihar',
                    'geometry' => ['location' => ['lat' => 25.6, 'lng' => 85.1]],
                ],
                'routes' => [[
                    'overview_polyline' => ['points' => 'abc'],
                    'legs' => [['distance' => ['value' => 12000], 'duration' => ['value' => 1800]]],
                ]],
            ], 200),
        ]);
    }

    public function test_public_catalog_quote_and_google_autocomplete(): void
    {
        $this->getJson('/api/v1/rides/catalog')
            ->assertOk()
            ->assertJsonPath('products.0.key', 'LOCAL_CAB');

        $this->getJson('/api/v1/places/autocomplete?q=Patna')
            ->assertOk()
            ->assertJsonPath('predictions.0.placeId', 'ChIJpickup');

        $quote = $this->postJson('/api/v1/rides/quote', [
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'distanceKm' => 10,
            'totalPaise' => 1,
        ])->assertUnprocessable();

        $ok = $this->postJson('/api/v1/rides/quote', [
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'distanceKm' => 10,
        ])->assertOk()->json();
        $this->assertSame('server', $ok['source']);
        $this->assertGreaterThan(0, $ok['totalPaise']);
    }

    public function test_customer_can_book_and_start_payment_without_client_discount(): void
    {
        $rider = User::factory()->create([
            'role' => OperatorRole::CUSTOMER,
            'nest_user_id' => 910,
        ]);

        $this->actingAs($rider)->postJson('/api/v1/rides/bookings', [
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'pickupText' => 'Patna Junction',
            'dropText' => 'Gandhi Maidan',
            'distanceKm' => 10,
            'passengerName' => 'Rakesh',
            'passengerPhone' => '9876543210',
            'discountPaise' => 500,
        ])->assertUnprocessable();

        $booking = $this->actingAs($rider)->postJson('/api/v1/rides/bookings', [
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'pickupText' => 'Patna Junction',
            'dropText' => 'Gandhi Maidan',
            'distanceKm' => 10,
            'passengerName' => 'Rakesh',
            'passengerPhone' => '9876543210',
        ])->assertCreated()->json();

        $this->assertSame('REQUESTED', $booking['status']);
        $this->assertGreaterThan(0, $booking['quotePaise']);

        $pay = $this->actingAs($rider)->postJson('/api/v1/rides/bookings/'.$booking['id'].'/payments', [
            'method' => 'upi',
        ])->assertCreated()->json();
        $this->assertSame('initiated', $pay['payment']['status']);
        $this->assertTrue($pay['payment']['clientCaptureIgnored']);
    }

    public function test_website_hmac_creates_booking_for_platform_customer(): void
    {
        $payload = [
            'product' => 'LOCAL_CAB',
            'category' => 'SEDAN',
            'pickupText' => 'Patna Junction',
            'dropText' => 'Gandhi Maidan',
            'distanceKm' => 8,
            'passengerName' => 'Site User',
            'passengerPhone' => '9000000001',
        ];
        $email = 'site@karnacab.local';
        $ts = (string) time();
        $body = json_encode($payload);
        $canonical = $ts."\nPOST\n/api/v1/rides/bookings\n".$body."\n".$email;
        $sig = hash_hmac('sha256', $canonical, 'karnacab-test-website');

        $created = $this->withHeaders([
            'X-Karnacab-Website-Timestamp' => $ts,
            'X-Karnacab-Website-Signature' => $sig,
            'X-Karnacab-Customer-Email' => $email,
            'X-Karnacab-Customer-Name' => 'Site User',
            'X-Karnacab-Customer-Phone' => '9000000001',
        ])->postJson('/api/v1/rides/bookings', $payload)->assertCreated()->json();

        $this->assertSame('REQUESTED', $created['status']);
        $this->assertTrue(DB::connection('platform')->table('users')->where('email', $email)->exists());
    }
}
