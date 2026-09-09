<?php

namespace App\Services;

use App\Events\DriverLocationUpdated;
use App\Platform\LiveFix;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class LiveLocationStore
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(int $driverId): ?array
    {
        $raw = Cache::get(LiveFix::driverKey($driverId));

        return is_array($raw) ? $raw : null;
    }

    /**
     * Hot path: cache only. Bookings are never written here.
     *
     * @param  array<string, mixed>  $fix
     */
    public function put(array $fix): void
    {
        $driverId = (int) $fix['driverId'];
        Cache::put(LiveFix::driverKey($driverId), $fix, LiveFix::CACHE_TTL_SECONDS);
        event(new DriverLocationUpdated($fix));
        $this->mirrorFirebase($fix);
    }

    /**
     * @param  list<int>  $driverIds
     * @return array<int, array<string, mixed>>
     */
    public function many(array $driverIds): array
    {
        $out = [];
        foreach ($driverIds as $id) {
            $fix = $this->get($id);
            if ($fix) {
                $out[$id] = $fix;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $fix
     */
    private function mirrorFirebase(array $fix): void
    {
        $url = rtrim((string) config('karnacab.firebase_database_url'), '/');
        $secret = (string) config('karnacab.firebase_database_secret');
        if ($url === '') {
            return;
        }
        try {
            $path = $url.'/live/drivers/'.$fix['driverId'].'.json';
            if ($secret !== '') {
                $path .= '?auth='.urlencode($secret);
            }
            Http::timeout(2)->put($path, $fix);
        } catch (Throwable) {
            // Firebase is optional; cache + poll remain the source of truth.
        }
    }
}
