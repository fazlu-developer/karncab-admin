<?php

namespace App\Platform;

final class SafetyPolicy
{
    public const TYPES = ['sos', 'complaint', 'lost_found', 'support'];

    public const STATUSES = ['open', 'investigating', 'resolved', 'closed'];

    public const SOS_KINDS = ['police', 'emergency', 'karnacab', 'share'];

    public const LIVE = [
        'ASSIGNED', 'DRIVER_ASSIGNED', 'DRIVER_ARRIVING', 'DRIVER_ARRIVED',
        'ONGOING', 'STARTED',
    ];

    public static function catalog(): array
    {
        return [
            'customer' => [
                'sos', 'emergency_contact', 'live_trip_sharing', 'driver_verification',
                'vehicle_verification', 'start_otp', 'end_otp', 'complaint', 'lost_found', 'support',
            ],
            'driver' => ['sos', 'emergency_contact', 'support', 'trip_sharing', 'start_otp', 'end_otp'],
            'incidentFields' => ['user', 'booking', 'location', 'timestamp', 'emergencyContact', 'status'],
            'types' => self::TYPES,
            'statuses' => self::STATUSES,
            'sosKinds' => self::SOS_KINDS,
            'note' => 'Share links use first names and plate last-4 only. Start and end OTPs never appear on share links or to the driver until the customer reads them aloud.',
        ];
    }

    public static function firstName(?string $name): string
    {
        $part = preg_split('/\s+/', trim((string) $name) ?: '')[0] ?? '';

        return $part !== '' ? $part : 'User';
    }

    public static function phoneLast4(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    public static function plateHint(?string $registration): ?string
    {
        $value = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $registration) ?? '');
        if ($value === '') {
            return null;
        }

        return strlen($value) <= 4 ? $value : '****'.substr($value, -4);
    }

    public static function indianMobile(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        $ten = strlen($digits) > 10 ? substr($digits, -10) : $digits;
        if (! preg_match('/^[6-9]\d{9}$/', $ten)) {
            return null;
        }

        return $ten;
    }

    public static function mapsUrl(mixed $lat, mixed $lng): ?string
    {
        if ($lat === null || $lng === null || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return 'https://maps.google.com/?q='.$lat.','.$lng;
    }

    public static function assertPin(string $kind, ?string $stored, ?string $provided): void
    {
        abort_if($stored === null || $stored === '', 403, $kind === 'start'
            ? 'Start PIN is not issued for this trip'
            : 'Completion PIN is not issued for this trip');
        $pin = trim((string) $provided);
        abort_if($pin === '', 403, $kind === 'start'
            ? 'Enter the customer start PIN to begin the trip'
            : 'Enter the customer completion PIN to finish the trip');
        abort_unless($pin === $stored, 403, $kind === 'start' ? 'Invalid start PIN' : 'Invalid completion PIN');
    }
}
