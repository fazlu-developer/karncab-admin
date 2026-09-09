<?php

namespace App\Platform;

final class RideCatalog
{
    public const PRODUCTS = [
        'LOCAL_CAB' => 'Local Cab',
        'ONE_WAY' => 'One Way',
        'ROUND_WAY' => 'Round Way',
        'RENTAL' => 'Rental',
        'SCHEDULE' => 'Schedule',
        'OUTSTATION' => 'Outstation',
        'AIRPORT' => 'Airport',
        'RAILWAY' => 'Railway',
        'MULTI_STOP' => 'Multi-stop',
    ];

    public const VEHICLES = [
        'BIKE' => ['label' => 'Bike', 'seats' => 1],
        'AUTO' => ['label' => 'Auto', 'seats' => 3],
        'E_RICKSHAW' => ['label' => 'E-Rickshaw', 'seats' => 3],
        'MINI' => ['label' => 'Mini', 'seats' => 4],
        'SEDAN' => ['label' => 'Sedan', 'seats' => 4],
        'SUV' => ['label' => 'SUV', 'seats' => 6],
        'TRAVELLER' => ['label' => 'Traveller', 'seats' => 12],
    ];

    public const RENTAL_HOURS = [2, 4, 6, 8, 12];

    public const SERVER_ROUTE_PRODUCTS = ['AIRPORT', 'RAILWAY', 'MULTI_STOP'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function products(): array
    {
        $rows = [];
        foreach (self::PRODUCTS as $key => $label) {
            $rows[] = ['key' => $key, 'title' => $label, 'label' => $label];
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function vehicles(): array
    {
        $rows = [];
        foreach (self::VEHICLES as $key => $meta) {
            $rows[] = ['key' => $key, 'title' => $meta['label'], 'label' => $meta['label'], 'seats' => $meta['seats']];
        }

        return $rows;
    }

    public static function assertProduct(string $product): string
    {
        abort_unless(isset(self::PRODUCTS[$product]), 422, 'Unknown ride product');

        return $product;
    }

    public static function assertCategory(string $category): string
    {
        abort_unless(isset(self::VEHICLES[$category]), 422, 'Unknown vehicle category');

        return $category;
    }
}
