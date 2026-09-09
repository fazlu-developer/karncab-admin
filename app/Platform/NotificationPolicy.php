<?php

namespace App\Platform;

final class NotificationPolicy
{
    public const CHANNELS = ['in_app', 'push', 'sms', 'email'];

    public const EVENTS = [
        'otp' => ['title' => 'One-time password', 'body' => 'KarnaCab login code: {code}', 'channels' => ['sms', 'email']],
        'booking_confirmation' => ['title' => 'Booking confirmed', 'body' => 'Your KarnaCab booking {ref} is confirmed.', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'driver_assigned' => ['title' => 'Driver assigned', 'body' => 'Your driver is on the way to pickup for {ref}.', 'channels' => ['in_app', 'push', 'sms']],
        'driver_arriving' => ['title' => 'Driver arriving', 'body' => 'Your driver is arriving at pickup.', 'channels' => ['in_app', 'push', 'sms']],
        'ride_started' => ['title' => 'Ride started', 'body' => 'Your ride has started. Share live trip from Safety if needed.', 'channels' => ['in_app', 'push']],
        'ride_completed' => ['title' => 'Ride completed', 'body' => 'Thanks for riding with KarnaCab. Rate your trip in bookings.', 'channels' => ['in_app', 'push', 'email']],
        'payment' => ['title' => 'Payment received', 'body' => 'Your payment {ref} was captured.', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'refund' => ['title' => 'Refund processed', 'body' => 'A refund of ₹{amount} was issued for {ref}.', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'cancellation' => ['title' => 'Booking cancelled', 'body' => 'Booking {ref} was cancelled.', 'channels' => ['in_app', 'push', 'sms']],
        'parcel_update' => ['title' => 'Parcel update', 'body' => 'Parcel {ref} is now {status}.', 'channels' => ['in_app', 'push', 'sms']],
        'document_expiry' => ['title' => 'Document expiry', 'body' => '{label} needs attention ({window}).', 'channels' => ['in_app', 'push', 'email']],
        'kyc_approval' => ['title' => 'KYC approved', 'body' => 'Your driver KYC is verified. You can go online.', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'kyc_rejection' => ['title' => 'KYC rejected', 'body' => 'Your KYC was rejected. {reason}', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'wallet_transaction' => ['title' => 'Wallet {direction}', 'body' => '₹{amount} {direction} · {note}', 'channels' => ['in_app', 'push', 'email']],
        'franchise_approval' => ['title' => 'Franchise approved', 'body' => 'Your franchise {ref} is now active.', 'channels' => ['in_app', 'push', 'sms', 'email']],
        'coupon' => ['title' => 'Coupon applied', 'body' => 'Coupon {code} saved ₹{amount} on booking {ref}.', 'channels' => ['in_app', 'push']],
        'support_update' => ['title' => 'Support update', 'body' => 'Ticket {ref} is {status}.', 'channels' => ['in_app', 'push', 'email']],
        'announcement' => ['title' => '{title}', 'body' => '{body}', 'channels' => ['in_app', 'push']],
    ];

    /**
     * @return array<string, mixed>
     */
    public static function catalog(): array
    {
        return [
            'channels' => self::CHANNELS,
            'events' => array_keys(self::EVENTS),
            'templates' => self::EVENTS,
            'architecture' => 'Modules call NotificationService::dispatch. Push, SMS, email, and in-app are sent from that service only.',
            'note' => 'OTP is SMS/email only so codes are not stored in the in-app inbox.',
        ];
    }

    /**
     * @param  array<string, mixed>  $vars
     * @return array{title: string, body: string, channels: list<string>}
     */
    public static function render(string $event, array $vars = []): array
    {
        $def = self::EVENTS[$event] ?? ['title' => '{title}', 'body' => '{body}', 'channels' => ['in_app', 'push']];

        return [
            'title' => self::fill($def['title'], $vars),
            'body' => self::fill($def['body'], $vars),
            'channels' => $def['channels'],
        ];
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public static function fill(string $template, array $vars): string
    {
        $out = preg_replace_callback('/\{(\w+)\}/', function (array $m) use ($vars) {
            $value = $vars[$m[1]] ?? '';

            return is_scalar($value) ? (string) $value : '';
        }, $template) ?? $template;

        return trim(preg_replace('/\s+/', ' ', $out) ?? $out);
    }
}
