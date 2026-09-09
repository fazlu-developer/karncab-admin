<?php

namespace App\Services;

use App\Models\User;
use App\Platform\RideCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class RideBookingService
{
    public function __construct(
        private readonly RideQuoteService $quotes,
        private readonly CouponService $coupons,
        private readonly PaymentService $payments,
        private readonly PlatformCustomerBinder $customers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(User $operator, array $input): array
    {
        abort_unless($operator->isCustomer() || $operator->can('bookings.manage'), 403, 'Only customers can request a ride');
        $this->coupons->rejectClientDiscount($input);
        $this->assertProductRules($input);
        $customerId = $this->customers->customerId($operator);
        $quote = $this->quotes->quote($input);
        $coupon = ['ok' => false, 'discountPaise' => 0, 'code' => null, 'couponId' => null];
        $couponId = null;
        if (! empty($input['couponCode'])) {
            $coupon = $this->coupons->quoteFor($operator, [
                'code' => $input['couponCode'],
                'fare_paise' => $quote['totalPaise'],
                'product' => $quote['product'],
                'district_id' => $input['districtId'] ?? null,
                'user_id' => $customerId,
            ]);
            if (! ($coupon['ok'] ?? false)) {
                throw ValidationException::withMessages([
                    'couponCode' => 'That coupon cannot be applied.',
                ]);
            }
            $couponId = DB::connection('platform')->table('coupons')->where('code', $coupon['code'])->value('id');
        }
        $payable = max(0, (int) $quote['totalPaise'] - (int) ($coupon['discountPaise'] ?? 0));
        $quoted = $quote + [
            'totalPaise' => $payable,
            'totalRupees' => $payable / 100,
            'couponCode' => $coupon['code'] ?? null,
            'couponDiscountPaise' => (int) ($coupon['discountPaise'] ?? 0),
        ];
        $status = $this->initialStatus($input);
        $row = [
            'public_ref' => $this->newRef(),
            'customer_id' => $customerId,
            'district_id' => $input['districtId'] ?? null,
            'product' => $quote['product'],
            'category' => $quote['category'],
            'status' => $status,
            'pickup_text' => $input['pickupText'],
            'drop_text' => $input['dropText'],
            'quote_paise' => $payable,
            'quote_snapshot' => json_encode($quoted),
            'coupon_id' => $couponId,
            'coupon_discount_paise' => (int) ($coupon['discountPaise'] ?? 0),
            'scheduled_at' => $this->ts($input['scheduledAt'] ?? null),
            'passenger_name' => $input['passengerName'] ?? $operator->name,
            'passenger_phone' => $input['passengerPhone'] ?? null,
            'booked_for_other' => filter_var($input['bookedForOther'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'start_otp' => (string) random_int(1000, 9999),
            'end_otp' => (string) random_int(1000, 9999),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        $this->putColumn($row, 'pickup_lat', $input['pickupLat'] ?? null);
        $this->putColumn($row, 'pickup_lng', $input['pickupLng'] ?? null);
        $this->putColumn($row, 'drop_lat', $input['dropLat'] ?? null);
        $this->putColumn($row, 'drop_lng', $input['dropLng'] ?? null);
        $this->putColumn($row, 'distance_km', $quote['distanceKm'] ?? null);
        $this->putColumn($row, 'polyline', $quote['polyline'] ?? null);
        $this->putColumn($row, 'return_at', $this->ts($input['returnAt'] ?? null));
        $this->putColumn($row, 'flight_number', $input['flightNumber'] ?? null);
        $this->putColumn($row, 'train_number', $input['trainNumber'] ?? null);
        $this->putColumn($row, 'terminal', $input['terminal'] ?? null);
        $this->putColumn($row, 'instructions', $input['instructions'] ?? null);

        $id = DB::connection('platform')->table('bookings')->insertGetId($row);

        return $this->one($operator, $id);
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        $q = DB::connection('platform')->table('bookings')->where('id', $id);
        if ($operator->isCustomer()) {
            $q->where('customer_id', $this->customers->customerId($operator));
        }
        $row = $q->first();
        abort_unless($row, 404, 'Booking not found');

        return $this->present($row);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function pay(User $operator, int $id, array $input): array
    {
        $booking = $this->one($operator, $id);
        $payment = $this->payments->initiate($operator, [
            'method' => $input['method'] ?? 'upi',
            'booking_id' => $booking['id'],
            'note' => $input['note'] ?? 'Website booking',
        ]);

        return [
            'booking' => $this->one($operator, $id),
            'payment' => $payment,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertProductRules(array $input): void
    {
        $product = RideCatalog::assertProduct((string) ($input['product'] ?? ''));
        if ($product === 'ROUND_WAY' && empty($input['returnAt'])) {
            abort(422, 'Round Way requires a return date and time');
        }
        if ($product === 'AIRPORT') {
            abort_unless(trim((string) ($input['flightNumber'] ?? '')) !== '', 422, 'Airport transfer requires a flight number');
            $this->assertFuture($input['scheduledAt'] ?? null, 'Airport transfer requires pickup date and time');
        }
        if ($product === 'RAILWAY') {
            abort_unless(trim((string) ($input['trainNumber'] ?? '')) !== '', 422, 'Railway transfer requires a train number');
            $this->assertFuture($input['scheduledAt'] ?? null, 'Railway transfer requires pickup date and time');
        }
        if ($product === 'SCHEDULE') {
            $this->assertFuture($input['scheduledAt'] ?? null, 'Schedule ride requires a future pickup time');
        }
    }

    private function assertFuture(mixed $value, string $message): void
    {
        abort_unless($this->ts($value) !== null && Carbon::parse($this->ts($value))->isFuture(), 422, $message);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function initialStatus(array $input): string
    {
        $product = (string) ($input['product'] ?? '');
        if (! empty($input['scheduledAt']) && in_array($product, ['SCHEDULE', 'AIRPORT', 'RAILWAY'], true)) {
            return 'CONFIRMED';
        }

        return 'REQUESTED';
    }

    private function ts(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->toDateTimeString();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function putColumn(array &$row, string $column, mixed $value): void
    {
        if (Schema::connection('platform')->hasColumn('bookings', $column)) {
            $row[$column] = $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        $snap = is_string($row->quote_snapshot ?? null) ? json_decode($row->quote_snapshot, true) : $row->quote_snapshot;
        $snap = is_array($snap) ? $snap : [];

        return [
            'id' => (int) $row->id,
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'product' => $row->product,
            'category' => $row->category,
            'pickupText' => $row->pickup_text,
            'dropText' => $row->drop_text,
            'scheduledAt' => $row->scheduled_at,
            'passengerName' => $row->passenger_name,
            'passengerPhone' => $row->passenger_phone,
            'quotePaise' => (int) ($row->quote_paise ?? 0),
            'quoteRupees' => ((int) ($row->quote_paise ?? 0)) / 100,
            'couponDiscountPaise' => (int) ($row->coupon_discount_paise ?? 0),
            'quote' => $snap,
            'note' => 'Booking stored on the platform. Payment capture is server-side.',
        ];
    }

    private function newRef(): string
    {
        return strtoupper('KC'.base_convert((string) ((int) (microtime(true) * 1000)), 10, 36).random_int(100, 999));
    }
}
