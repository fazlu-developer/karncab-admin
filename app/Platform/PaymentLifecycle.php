<?php

namespace App\Platform;

final class PaymentLifecycle
{
    public const METHODS = ['cash', 'upi', 'card', 'wallet', 'advance', 'partial'];

    public const PAYMENT_STEPS = ['created', 'pending', 'initiated', 'success', 'failed'];

    public const REFUND_STEPS = ['requested', 'approved', 'processing', 'completed'];

    public const SUCCESS_ALIASES = ['success', 'captured', 'paid'];

    public const AWAITING_CAPTURE = ['created', 'pending', 'initiated'];

    public static function normalizeMethod(string $raw): string
    {
        $value = strtolower(trim($raw));
        if (in_array($value, ['cod', 'cash_on_delivery'], true)) {
            return 'cash';
        }
        if (in_array($value, ['razorpay', 'gpay', 'phonepe', 'paytm'], true)) {
            return 'upi';
        }
        if (in_array($value, ['credit', 'debit', 'netbanking'], true)) {
            return 'card';
        }
        if (in_array($value, self::METHODS, true)) {
            return $value;
        }
        abort(422, 'Unsupported payment method');
    }

    public static function intentFor(string $method, ?string $explicit = null): string
    {
        if ($explicit === 'advance' || $method === 'advance') {
            return 'advance';
        }
        if ($explicit === 'partial' || $method === 'partial') {
            return 'partial';
        }
        if ($explicit === 'cancel_fee') {
            return 'cancel_fee';
        }
        if ($explicit === 'refund') {
            return 'refund';
        }

        return 'capture';
    }

    public static function needsWebhook(string $method): bool
    {
        return in_array($method, ['upi', 'card', 'advance', 'partial'], true);
    }

    public static function chargeMethod(string $method): string
    {
        return in_array($method, ['advance', 'partial'], true) ? 'upi' : $method;
    }

    public static function isSuccess(string $status): bool
    {
        return in_array(strtolower($status), self::SUCCESS_ALIASES, true);
    }

    public static function isAwaitingCapture(string $status): bool
    {
        return in_array(strtolower($status), self::AWAITING_CAPTURE, true);
    }

    public static function displayPaymentStatus(string $status): string
    {
        $value = strtolower($status);
        if (self::isSuccess($value)) {
            return 'success';
        }

        return $value;
    }

    public static function canRetry(string $status, string $kind): bool
    {
        return $kind === 'payment' && in_array(strtolower($status), ['failed', 'pending', 'created', 'initiated'], true);
    }

    public static function canRefund(string $status): bool
    {
        return self::isSuccess($status) || in_array(strtolower($status), ['partially_refunded'], true);
    }

    public static function invoiceStatus(int $paidPaise, int $refundedPaise, int $totalPaise): string
    {
        if ($refundedPaise > 0 && $paidPaise - $refundedPaise <= 0) {
            return 'refunded';
        }
        if ($paidPaise <= 0) {
            return 'issued';
        }
        if ($paidPaise - $refundedPaise >= $totalPaise) {
            return 'paid';
        }

        return 'partial';
    }

    public static function webhookCanonical(string $event, string $paymentRef, int $amountPaise): string
    {
        return $event.'|'.$paymentRef.'|'.$amountPaise;
    }

    public static function signWebhook(string $secret, string $canonical): string
    {
        return hash_hmac('sha256', $canonical, $secret);
    }

    public static function verifyWebhook(string $secret, string $canonical, ?string $signature): bool
    {
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }
        $expected = self::signWebhook($secret, $canonical);

        return hash_equals($expected, strtolower(trim($signature)));
    }
}
