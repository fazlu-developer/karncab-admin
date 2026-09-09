<?php

return [
    // Management console talks to MySQL platform tables. Flutter apps still use Nest.
    'google_maps_key' => env('GOOGLE_MAPS_API_KEY', env('GOOGLE_MAPS_API')),
    'website_booking_secret' => env('WEBSITE_BOOKING_SECRET', 'karnacab-dev-website'),
    'firebase_database_url' => env('FIREBASE_DATABASE_URL'),
    'firebase_database_secret' => env('FIREBASE_DATABASE_SECRET'),
    'live_map_poll_ms' => (int) env('LIVE_MAP_POLL_MS', 4000),
    'payment_gateway' => env('PAYMENT_GATEWAY', 'demo'),
    'payment_webhook_secret' => env('PAYMENT_WEBHOOK_SECRET', env('JWT_SECRET', 'karnacab-dev-jwt')),
];
