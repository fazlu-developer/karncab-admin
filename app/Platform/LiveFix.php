<?php

namespace App\Platform;

final class LiveFix
{
    public const CACHE_TTL_SECONDS = 180;

    public const STALE_AFTER_MS = 45000;

    /**
     * @return array<string, mixed>
     */
    public static function make(
        int $driverId,
        ?int $vehicleId,
        ?int $bookingId,
        ?int $districtId,
        float $lat,
        float $lng,
        ?float $heading,
        ?float $speed,
        string $tripStatus,
        int $recordedAtMs,
    ): array {
        return [
            'driverId' => (string) $driverId,
            'vehicleId' => $vehicleId ? (string) $vehicleId : null,
            'bookingId' => $bookingId ? (string) $bookingId : null,
            'districtId' => $districtId,
            'lat' => $lat,
            'lng' => $lng,
            'heading' => $heading,
            'speed' => $speed,
            'tripStatus' => substr($tripStatus, 0, 32),
            'recordedAt' => gmdate('c', (int) floor($recordedAtMs / 1000)),
            'persistedAt' => null,
        ];
    }

    public static function driverKey(int $driverId): string
    {
        return 'kc:loc:driver:'.$driverId;
    }

    public static function metersBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $toRad = static fn (float $value): float => $value * M_PI / 180;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;

        return 6371000 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param  array<string, mixed>|null  $prev
     */
    public static function shouldSkipPing(?array $prev, float $lat, float $lng, int $recordedAtMs): bool
    {
        if ($prev === null) {
            return false;
        }
        $prevAt = strtotime((string) ($prev['recordedAt'] ?? '')) * 1000;
        if ($prevAt <= 0) {
            return false;
        }
        $dt = $recordedAtMs - $prevAt;

        return $dt < 800 && self::metersBetween((float) $prev['lat'], (float) $prev['lng'], $lat, $lng) < 5;
    }

    /**
     * Persist last-known coordinates to users/vehicles only when the driver moved or 15s elapsed.
     * Never write a GPS trail onto bookings.
     *
     * @param  array<string, mixed>|null  $prev
     */
    public static function shouldPersist(?array $prev, float $lat, float $lng, int $recordedAtMs): bool
    {
        if ($prev === null) {
            return true;
        }
        $prevAt = strtotime((string) ($prev['recordedAt'] ?? '')) * 1000;
        if ($prevAt <= 0 || $recordedAtMs - $prevAt >= 15000) {
            return true;
        }

        return self::metersBetween((float) $prev['lat'], (float) $prev['lng'], $lat, $lng) >= 40;
    }

    public static function isStale(?string $recordedAt, ?int $nowMs = null): bool
    {
        if (! $recordedAt) {
            return true;
        }
        $at = strtotime($recordedAt);
        if ($at === false) {
            return true;
        }
        $now = $nowMs ?? (int) floor(microtime(true) * 1000);

        return ($now - $at * 1000) > self::STALE_AFTER_MS;
    }
}
