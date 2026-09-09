<?php

namespace App\Services;

use App\Platform\FareQuoteEngine;
use App\Platform\RideCatalog;
use App\Services\CouponService;
use Throwable;

class RideQuoteService
{
    public function __construct(
        private readonly FareQuoteEngine $fares,
        private readonly GooglePlacesService $maps,
        private readonly CouponService $coupons,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return [
            'products' => RideCatalog::products(),
            'vehicles' => RideCatalog::vehicles(),
            'rentalHours' => RideCatalog::RENTAL_HOURS,
            'rentalPackages' => $this->fares->rentalPackages(),
            'paymentMethods' => ['upi', 'card', 'wallet', 'cash'],
            'note' => 'Fare quotes and bookings use Laravel fare_rules. Discount amounts from the client are ignored.',
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function quote(array $input): array
    {
        $this->coupons->rejectClientDiscount($input);
        $product = RideCatalog::assertProduct((string) ($input['product'] ?? ''));
        $category = RideCatalog::assertCategory((string) ($input['category'] ?? 'SEDAN'));
        $route = $this->resolveRoute($input, $product);
        $quote = $this->fares->quote([
            'product' => $product,
            'category' => $category,
            'distanceKm' => $route['distanceKm'],
            'districtId' => isset($input['districtId']) ? (int) $input['districtId'] : null,
            'waitMinutes' => $input['waitMinutes'] ?? 0,
            'night' => $input['night'] ?? false,
            'tollPaise' => $input['tollPaise'] ?? 0,
            'parkingPaise' => $input['parkingPaise'] ?? 0,
            'hours' => $input['hours'] ?? null,
            'extraHours' => $input['extraHours'] ?? 0,
            'stopCount' => is_array($input['stops'] ?? null) ? count($input['stops']) : ($input['stopCount'] ?? 0),
            'roundTrip' => $input['roundTrip'] ?? true,
            'nightStayNights' => $input['nightStayNights'] ?? 0,
        ]);
        $quote['distanceKm'] = $route['distanceKm'];
        $quote['durationMinutes'] = $route['durationMinutes'];
        $quote['polyline'] = $route['polyline'];

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{distanceKm: float, durationMinutes: int|null, polyline: string|null}
     */
    public function resolveRoute(array $input, string $product): array
    {
        $fallback = max(1.0, (float) ($input['distanceKm'] ?? 1));
        $pickupLat = isset($input['pickupLat']) ? (float) $input['pickupLat'] : null;
        $pickupLng = isset($input['pickupLng']) ? (float) $input['pickupLng'] : null;
        $dropLat = isset($input['dropLat']) ? (float) $input['dropLat'] : null;
        $dropLng = isset($input['dropLng']) ? (float) $input['dropLng'] : null;
        $hasCoords = $pickupLat !== null && $pickupLng !== null && $dropLat !== null && $dropLng !== null;
        $wantGoogle = $hasCoords && (
            in_array($product, RideCatalog::SERVER_ROUTE_PRODUCTS, true)
            || empty($input['distanceKm'])
        );
        if ($wantGoogle) {
            try {
                $waypoints = [];
                foreach (is_array($input['stops'] ?? null) ? $input['stops'] : [] as $stop) {
                    if (isset($stop['lat'], $stop['lng'])) {
                        $waypoints[] = ['lat' => (float) $stop['lat'], 'lng' => (float) $stop['lng']];
                    }
                }
                $route = $this->maps->directions($pickupLat, $pickupLng, $dropLat, $dropLng, $waypoints);

                return [
                    'distanceKm' => max(1.0, (float) $route['distanceKm']),
                    'durationMinutes' => $route['durationMinutes'],
                    'polyline' => $route['polyline'] ?: null,
                ];
            } catch (Throwable) {
                if ($hasCoords) {
                    $km = $this->maps->haversineKm($pickupLat, $pickupLng, $dropLat, $dropLng);

                    return [
                        'distanceKm' => max(1.0, $km),
                        'durationMinutes' => max(1, (int) round($km * 2)),
                        'polyline' => $input['polyline'] ?? null,
                    ];
                }
            }
        }

        return [
            'distanceKm' => $fallback,
            'durationMinutes' => isset($input['durationMinutes']) ? (int) $input['durationMinutes'] : null,
            'polyline' => $input['polyline'] ?? null,
        ];
    }
}
