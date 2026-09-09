<?php

namespace App\Platform;

final class CustomerDashboard
{
    public const HISTORY = ['COMPLETED', 'cancelled', 'CANCELLED'];

    public const ACTIVE = [
        'DRIVER_ARRIVING', 'DRIVER_ARRIVED', 'STARTED', 'ONGOING',
    ];

    public const UPCOMING = [
        'REQUESTED', 'PENDING', 'CONFIRMED', 'DRIVER_SEARCHING',
        'ASSIGNED', 'DRIVER_ASSIGNED', 'QUOTED',
    ];

    /**
     * @return list<array{key: string, label: string, icon: string, group: string}>
     */
    public static function tools(): array
    {
        return [
            ['key' => 'profile', 'label' => 'Profile', 'icon' => 'user', 'group' => 'Account'],
            ['key' => 'emergency', 'label' => 'Emergency contacts', 'icon' => 'phone', 'group' => 'Account'],
            ['key' => 'sos', 'label' => 'SOS', 'icon' => 'shield-alert', 'group' => 'Support'],
            ['key' => 'share', 'label' => 'Trip sharing', 'icon' => 'share-2', 'group' => 'Support'],
            ['key' => 'verify', 'label' => 'Driver verification', 'icon' => 'badge-check', 'group' => 'Support'],
            ['key' => 'places', 'label' => 'Saved locations', 'icon' => 'map-pin', 'group' => 'Account'],
            ['key' => 'family', 'label' => 'Family booking', 'icon' => 'users', 'group' => 'Account'],
            ['key' => 'guest', 'label' => 'Booking for another person', 'icon' => 'user-plus', 'group' => 'Account'],
            ['key' => 'history', 'label' => 'Booking history', 'icon' => 'history', 'group' => 'Rides'],
            ['key' => 'upcoming', 'label' => 'Upcoming bookings', 'icon' => 'calendar-clock', 'group' => 'Rides'],
            ['key' => 'scheduled', 'label' => 'Scheduled rides', 'icon' => 'calendar', 'group' => 'Rides'],
            ['key' => 'active', 'label' => 'Active ride', 'icon' => 'navigation', 'group' => 'Rides'],
            ['key' => 'parcels', 'label' => 'Parcel history', 'icon' => 'package', 'group' => 'Rides'],
            ['key' => 'travel', 'label' => 'Travel bookings', 'icon' => 'map', 'group' => 'Rides'],
            ['key' => 'bulk', 'label' => 'Bulk bookings', 'icon' => 'layers', 'group' => 'Rides'],
            ['key' => 'corporate', 'label' => 'Corporate bookings', 'icon' => 'building-2', 'group' => 'Rides'],
            ['key' => 'wallet', 'label' => 'Wallet', 'icon' => 'wallet', 'group' => 'Money'],
            ['key' => 'coupons', 'label' => 'Coupons', 'icon' => 'ticket-percent', 'group' => 'Money'],
            ['key' => 'offers', 'label' => 'Offers', 'icon' => 'sparkles', 'group' => 'Money'],
            ['key' => 'invoices', 'label' => 'Invoices', 'icon' => 'file-text', 'group' => 'Money'],
            ['key' => 'notifications', 'label' => 'Notifications', 'icon' => 'bell', 'group' => 'Support'],
            ['key' => 'ratings', 'label' => 'Ratings', 'icon' => 'star', 'group' => 'Support'],
            ['key' => 'complaints', 'label' => 'Complaints', 'icon' => 'message-circle-warning', 'group' => 'Support'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        return [
            'tools' => array_column(self::tools(), 'key'),
            'sections' => self::tools(),
            'passengerFields' => ['passengerName', 'passengerMobile', 'pickup', 'destination', 'instructions'],
            'note' => 'The booker pays. The passenger is who the driver meets. Drivers never receive the booker phone when booking for someone else.',
        ];
    }

    public static function bucket(string $status, string $product = 'LOCAL_CAB', mixed $scheduledAt = null): string
    {
        $status = strtoupper($status);
        if (in_array($status, self::HISTORY, true) || in_array($status, ['COMPLETED', 'CANCELLED'], true)) {
            return 'history';
        }
        if (in_array($status, self::ACTIVE, true)) {
            return 'active';
        }
        $scheduled = $product === 'SCHEDULE' || ($scheduledAt && \Illuminate\Support\Carbon::parse($scheduledAt)->isFuture());
        if ($scheduled) {
            return 'scheduled';
        }

        return 'upcoming';
    }
}
