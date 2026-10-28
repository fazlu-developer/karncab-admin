<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdvancePayment
{
    public const PRODUCTS = [
        'LOCAL_CAB' => 'City ride',
        'ONE_WAY' => 'One way',
        'ROUND_WAY' => 'Round way',
        'SCHEDULE' => 'Schedule ride',
        'AIRPORT' => 'Airport transfer',
        'RAILWAY' => 'Railway transfer',
        'RENTAL' => 'Cab rental',
        'MULTI_STOP' => 'Multi stop',
        'PARCEL' => 'Parcel',
        'TRAVEL' => 'Tour & Travel',
        'BULK' => 'Bulk booking',
        'CORPORATE' => 'Corporate',
    ];

    public static function defaultPercents(): array
    {
        return [
            'LOCAL_CAB' => 0,
            'ONE_WAY' => 10,
            'ROUND_WAY' => 10,
            'SCHEDULE' => 10,
            'AIRPORT' => 10,
            'RAILWAY' => 10,
            'RENTAL' => 10,
            'MULTI_STOP' => 10,
            'PARCEL' => 10,
            'TRAVEL' => 30,
            'BULK' => 10,
            'CORPORATE' => 10,
        ];
    }

    /**
     * @return array<string, int>
     */
    public static function percentMap(): array
    {
        $defaults = self::defaultPercents();
        if (! Schema::connection('platform')->hasTable('system_settings')) {
            return $defaults;
        }
        $db = DB::connection('platform');
        $percentsRaw = $db->table('system_settings')->where('key', 'advance_payment_percents')->value('value');
        $percents = is_string($percentsRaw) ? json_decode($percentsRaw, true) : null;
        $flagsRaw = $db->table('system_settings')->where('key', 'advance_payment_products')->value('value');
        $flags = is_string($flagsRaw) ? json_decode($flagsRaw, true) : null;
        $out = [];
        foreach ($defaults as $key => $fallback) {
            if (is_array($percents) && array_key_exists($key, $percents) && is_numeric($percents[$key])) {
                $out[$key] = max(0, min(100, (int) round((float) $percents[$key])));
                continue;
            }
            if (is_array($flags) && array_key_exists($key, $flags)) {
                $on = $flags[$key] === true || in_array(strtolower((string) $flags[$key]), ['1', 'true', 'yes', 'on'], true);
                $out[$key] = $on ? $fallback : 0;
                continue;
            }
            $out[$key] = $fallback;
        }

        return $out;
    }

    public static function percent(string $product): int
    {
        $product = strtoupper($product);
        if ($product === 'CITY_RIDE' || $product === 'CITY') {
            $product = 'LOCAL_CAB';
        }
        if ($product === 'TOUR') {
            $product = 'TRAVEL';
        }
        if ($product === 'AMBULANCE') {
            return 0;
        }

        return (int) (self::percentMap()[$product] ?? 0);
    }
}
