<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GooglePlacesService
{
    public function autocomplete(string $query, ?string $types = null): array
    {
        $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json';
        $payload = $this->get($url, array_filter([
            'input' => $query,
            'key' => $this->key(),
            'components' => 'country:in',
            'language' => 'en',
            'types' => $types,
        ], fn ($value) => $value !== null && $value !== ''));

        return array_map(function (array $row) {
            $fmt = is_array($row['structured_formatting'] ?? null) ? $row['structured_formatting'] : [];

            return [
                'placeId' => (string) ($row['place_id'] ?? ''),
                'title' => (string) ($fmt['main_text'] ?? $row['description'] ?? ''),
                'subtitle' => (string) ($fmt['secondary_text'] ?? ''),
                'address' => (string) ($row['description'] ?? ''),
            ];
        }, is_array($payload['predictions'] ?? null) ? $payload['predictions'] : []);
    }

    /**
     * @return array{placeId: string, title: string, address: string, lat: float, lng: float}
     */
    public function details(string $placeId): array
    {
        $payload = $this->get('https://maps.googleapis.com/maps/api/place/details/json', [
            'place_id' => $placeId,
            'fields' => 'geometry,formatted_address,name',
            'key' => $this->key(),
        ]);
        $result = is_array($payload['result'] ?? null) ? $payload['result'] : [];
        $loc = is_array($result['geometry']['location'] ?? null) ? $result['geometry']['location'] : [];

        return [
            'placeId' => $placeId,
            'title' => (string) ($result['name'] ?? $result['formatted_address'] ?? ''),
            'address' => (string) ($result['formatted_address'] ?? $result['name'] ?? ''),
            'lat' => (float) ($loc['lat'] ?? 0),
            'lng' => (float) ($loc['lng'] ?? 0),
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $waypoints
     * @return array{distanceKm: float, durationMinutes: int, polyline: string}
     */
    public function directions(float $originLat, float $originLng, float $destLat, float $destLng, array $waypoints = []): array
    {
        $params = [
            'origin' => $originLat.','.$originLng,
            'destination' => $destLat.','.$destLng,
            'mode' => 'driving',
            'key' => $this->key(),
        ];
        if ($waypoints !== []) {
            $params['waypoints'] = implode('|', array_map(fn ($p) => $p['lat'].','.$p['lng'], $waypoints));
        }
        $payload = $this->get('https://maps.googleapis.com/maps/api/directions/json', $params);
        $route = is_array($payload['routes'][0] ?? null) ? $payload['routes'][0] : [];
        $legs = is_array($route['legs'] ?? null) ? $route['legs'] : [];
        $meters = 0;
        $seconds = 0;
        foreach ($legs as $leg) {
            $meters += (int) ($leg['distance']['value'] ?? 0);
            $seconds += (int) ($leg['duration']['value'] ?? 0);
        }
        $distanceKm = $meters > 0
            ? round($meters / 1000, 2)
            : $this->pathKm(array_merge([['lat' => $originLat, 'lng' => $originLng]], $waypoints, [['lat' => $destLat, 'lng' => $destLng]]));

        return [
            'distanceKm' => $distanceKm,
            'durationMinutes' => max(1, (int) round($seconds / 60) ?: (int) max(1, round($distanceKm * 2))),
            'polyline' => (string) ($route['overview_polyline']['points'] ?? ''),
        ];
    }

    /**
     * @param  list<array{lat: float, lng: float}>  $points
     */
    public function pathKm(array $points): float
    {
        $km = 0.0;
        for ($i = 1; $i < count($points); $i++) {
            $km += $this->haversineKm($points[$i - 1]['lat'], $points[$i - 1]['lng'], $points[$i]['lat'], $points[$i]['lng']);
        }

        return round($km, 2);
    }

    public function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = fn (float $v) => ($v * M_PI) / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return round(6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function key(): string
    {
        $key = (string) config('karnacab.google_maps_key');
        abort_unless($key !== '', 503, 'Google Maps API key is not configured');

        return $key;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function get(string $url, array $query): array
    {
        $response = Http::timeout(8)->acceptJson()->get($url, $query);
        abort_unless($response->successful(), 503, 'Google Maps request failed');
        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [];
        $status = (string) ($payload['status'] ?? '');
        if (! in_array($status, ['OK', 'ZERO_RESULTS'], true)) {
            throw new RuntimeException((string) ($payload['error_message'] ?? ('Google Maps '.($status !== '' ? $status : 'request failed'))));
        }

        return $payload;
    }
}
