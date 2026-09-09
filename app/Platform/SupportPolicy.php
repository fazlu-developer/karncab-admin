<?php

namespace App\Platform;

final class SupportPolicy
{
    public const STATUSES = ['open', 'assigned', 'in_progress', 'resolved', 'closed'];

    public const LEGACY = ['pending' => 'in_progress', 'waiting' => 'in_progress'];

    public const CATEGORIES = [
        'booking' => 'Booking',
        'payment' => 'Payment',
        'driver' => 'Driver',
        'safety' => 'Safety',
        'wallet' => 'Wallet',
        'parcel' => 'Parcel',
        'travel' => 'Travel',
        'other' => 'Other',
    ];

    public const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public const KINDS = ['complaint', 'support', 'parcel', 'travel', 'bulk', 'corporate', 'driver'];

    public const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'image/jpg'];

    /**
     * @return array<string, list<string>>
     */
    public static function transitions(): array
    {
        return [
            'open' => ['assigned'],
            'assigned' => ['in_progress'],
            'in_progress' => ['resolved'],
            'resolved' => ['closed'],
            'closed' => [],
        ];
    }

    public static function catalog(): array
    {
        return [
            'statuses' => self::STATUSES,
            'flow' => 'Open → Assigned → In Progress → Resolved → Closed',
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
            'kinds' => self::KINDS,
            'attachments' => self::MIMES,
            'fields' => [
                'ticketId', 'user', 'booking', 'category', 'subject', 'description',
                'attachments', 'priority', 'status', 'assignedAgent', 'resolution',
                'createdAt', 'closedAt',
            ],
            'note' => 'Users open tickets. Agents assign, work, resolve, then close. History records every status change, reply, and attachment.',
        ];
    }

    public static function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));
        if (isset(self::LEGACY[$status])) {
            return self::LEGACY[$status];
        }

        return in_array($status, self::STATUSES, true) ? $status : 'open';
    }

    public static function canAdvance(string $from, string $to): bool
    {
        $from = self::normalize($from);
        $to = self::normalize($to);

        return in_array($to, self::transitions()[$from] ?? [], true);
    }
}
