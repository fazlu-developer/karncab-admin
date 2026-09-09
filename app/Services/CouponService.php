<?php

namespace App\Services;

use App\Models\User;
use App\Platform\CouponEngine;
use App\Platform\LoyaltyEngine;
use App\Platform\OperatorRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponService
{
    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return CouponEngine::catalog() + [
            'loyalty' => [
                'pointsPerCompletedRide' => $this->settingInt('loyalty_points_per_ride', 10),
                'paisePerPoint' => $this->settingInt('loyalty_paise_per_point', 100),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function workspace(User $operator, array $query = []): array
    {
        abort_unless($operator->can('platform.admin'), 403);

        return [
            'catalog' => $this->catalog(),
            'coupons' => $this->list($operator, $query),
            'loyalty' => $this->loyaltyAccounts(),
            'states' => $this->db()->table('states')->orderBy('name')->get(['id', 'name'])->all(),
            'districts' => $this->db()->table('districts')->orderBy('name')->get(['id', 'state_id', 'name'])->all(),
            'query' => $query,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(User $operator, array $query = []): array
    {
        abort_unless($operator->can('platform.admin'), 403);
        $q = $this->db()->table('coupons as coupons')
            ->leftJoin('states', 'states.id', '=', 'coupons.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'coupons.district_id')
            ->select('coupons.*', 'states.name as state_name', 'districts.name as district_name');
        if (! empty($query['q'])) {
            $term = '%'.$query['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('coupons.code', 'like', $term)->orWhere('coupons.title', 'like', $term);
            });
        }
        if (isset($query['active']) && $query['active'] !== '') {
            $q->where('coupons.active', (bool) $query['active']);
        }

        return array_map(fn ($row) => $this->present($row), $q->orderByDesc('coupons.id')->limit(200)->get()->all());
    }

    /**
     * Active offers for the signed-in rider. Still quoted server-side on apply.
     *
     * @return list<array<string, mixed>>
     */
    public function offers(User $operator): array
    {
        $userId = (int) ($operator->nest_user_id ?: $operator->id);
        $user = $this->db()->table('users')->where('id', $userId)->first();
        $rows = $this->db()->table('coupons')->where('active', true)
            ->where('starts_on', '<=', now())
            ->where('ends_on', '>=', now())
            ->orderBy('code')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $quoted = CouponEngine::quote((array) $row, [
                'farePaise' => 1_000_000,
                'product' => $row->product,
                'stateId' => $operator->state_id ?? $user->state_id ?? null,
                'districtId' => $operator->district_id ?? $user->district_id ?? null,
                'role' => $user->role ?? $operator->role,
                'bookingCount' => $this->db()->table('bookings')->where('customer_id', $userId)->count(),
                'completedRides' => $this->db()->table('bookings')->where('customer_id', $userId)->whereIn('status', ['COMPLETED', 'completed'])->count(),
                'usageCount' => $this->db()->table('coupon_redemptions')->where('coupon_id', $row->id)->count(),
                'userUsageCount' => $this->db()->table('coupon_redemptions')->where('coupon_id', $row->id)->where('user_id', $userId)->count(),
            ]);
            if ($quoted['ok'] || in_array($quoted['reason'], ['min_fare', 'no_discount'], true)) {
                $view = $this->present($row);
                $view['offer'] = ['title' => $row->title, 'subtitle' => $row->subtitle, 'cta' => 'Use '.$row->code];
                $out[] = $view;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function one(User $operator, int $id): array
    {
        abort_unless($operator->can('platform.admin'), 403);
        $row = $this->find($id);
        abort_if($row === null, 404, 'Coupon not found');
        $view = $this->present($row);
        $view['redemptions'] = $this->db()->table('coupon_redemptions')->where('coupon_id', $id)->orderByDesc('id')->limit(50)->get()->all();

        return $view;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsert(User $operator, array $data, ?int $id = null): array
    {
        abort_unless($operator->can('platform.admin'), 403);
        $code = strtoupper(trim((string) $data['code']));
        $existing = $this->db()->table('coupons')->where('code', $code)->first();
        if ($existing && ($id === null || (int) $existing->id !== $id)) {
            abort(400, 'Coupon code already exists');
        }
        $this->assertWindow($data['starts_on'], $data['ends_on']);
        $kind = $data['kind'] ?? ((int) ($data['percent'] ?? 0) > 0 ? 'percent' : 'fixed');
        $payload = [
            'code' => $code,
            'title' => trim((string) $data['title']),
            'subtitle' => isset($data['subtitle']) ? (trim((string) $data['subtitle']) ?: null) : null,
            'kind' => $kind,
            'percent' => $kind === 'percent' ? max(0, min(100, (int) ($data['percent'] ?? 0))) : 0,
            'amount_paise' => $kind === 'fixed' ? max(0, (int) ($data['amount_paise'] ?? 0)) : 0,
            'max_discount_paise' => max(0, (int) ($data['max_discount_paise'] ?? 0)),
            'min_fare_paise' => max(0, (int) ($data['min_fare_paise'] ?? 0)),
            'product' => $data['product'] ?? null,
            'state_id' => $this->nullableInt($data['state_id'] ?? null),
            'district_id' => $this->nullableInt($data['district_id'] ?? null),
            'audience' => $data['audience'] ?? 'all',
            'usage_limit' => max(0, (int) ($data['usage_limit'] ?? 0)),
            'user_limit' => max(0, (int) ($data['user_limit'] ?? 0)),
            'starts_on' => Carbon::parse($data['starts_on']),
            'ends_on' => Carbon::parse($data['ends_on']),
            'active' => array_key_exists('active', $data) ? (bool) $data['active'] : true,
            'updated_at' => now(),
        ];
        if ($payload['state_id']) {
            abort_unless($this->db()->table('states')->where('id', $payload['state_id'])->exists(), 400, 'Unknown state');
        }
        if ($payload['district_id']) {
            $district = $this->db()->table('districts')->where('id', $payload['district_id'])->first();
            abort_unless($district !== null, 400, 'Unknown district');
            if ($payload['state_id'] && (int) $district->state_id !== $payload['state_id']) {
                abort(400, 'District is not in the selected state');
            }
            $payload['state_id'] = $payload['state_id'] ?: (int) $district->state_id;
        }
        if ($id) {
            abort_unless($this->db()->table('coupons')->where('id', $id)->exists(), 404, 'Coupon not found');
            $this->db()->table('coupons')->where('id', $id)->update($payload);
        } else {
            $payload['created_at'] = now();
            $id = $this->db()->table('coupons')->insertGetId($payload);
        }

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(User $operator, array $input): array
    {
        $this->rejectClientDiscount($input);
        $quoted = $this->quoteFor($operator, $input);
        if (! $quoted['ok']) {
            throw ValidationException::withMessages([
                'code' => CouponEngine::message($quoted['reason']),
            ]);
        }
        $fare = max(0, (int) ($input['fare_paise'] ?? 0));

        return $quoted + [
            'farePaise' => $fare,
            'payablePaise' => max(0, $fare - $quoted['discountPaise']),
            'clientDiscountIgnored' => true,
        ];
    }

    /**
     * Apply a coupon to a booking. Discount is recomputed on the server.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function apply(User $operator, array $input): array
    {
        $this->rejectClientDiscount($input);
        $bookingId = (int) $input['booking_id'];
        $booking = $this->db()->table('bookings')->where('id', $bookingId)->first();
        abort_if($booking === null, 404, 'Booking not found');
        $this->assertBookingAccess($operator, $booking);
        $fare = (int) ($booking->quote_paise ?? 0);
        $district = $booking->district_id
            ? $this->db()->table('districts')->where('id', $booking->district_id)->first()
            : null;
        $quoted = $this->quoteFor($operator, [
            'code' => $input['code'] ?? '',
            'fare_paise' => $fare,
            'product' => $booking->product,
            'state_id' => $district->state_id ?? null,
            'district_id' => $booking->district_id,
            'user_id' => $booking->customer_id,
        ]);
        if (! $quoted['ok']) {
            throw ValidationException::withMessages([
                'code' => CouponEngine::message($quoted['reason']),
            ]);
        }
        $coupon = $this->byCode((string) $input['code']);
        $this->db()->transaction(function () use ($booking, $coupon, $quoted) {
            $this->db()->table('bookings')->where('id', $booking->id)->update([
                'coupon_id' => $coupon->id,
                'coupon_discount_paise' => $quoted['discountPaise'],
                'updated_at' => now(),
            ]);
            $existing = $this->db()->table('coupon_redemptions')->where('booking_id', $booking->id)->first();
            if ($existing) {
                $this->db()->table('coupon_redemptions')->where('id', $existing->id)->update([
                    'coupon_id' => $coupon->id,
                    'discount_paise' => $quoted['discountPaise'],
                ]);
            } else {
                $this->db()->table('coupon_redemptions')->insert([
                    'coupon_id' => $coupon->id,
                    'user_id' => $booking->customer_id,
                    'booking_id' => $booking->id,
                    'discount_paise' => $quoted['discountPaise'],
                    'created_at' => now(),
                ]);
            }
        });

        app(NotificationService::class)->dispatch((int) $booking->customer_id, 'coupon', [
            'code' => $coupon->code,
            'amount' => number_format($quoted['discountPaise'] / 100, 2, '.', ''),
            'ref' => $booking->public_ref ?? (string) $booking->id,
        ], ['entity' => ['type' => 'booking', 'id' => (string) $booking->id]]);

        return $quoted + [
            'bookingId' => (int) $booking->id,
            'farePaise' => $fare,
            'payablePaise' => max(0, $fare - $quoted['discountPaise']),
            'clientDiscountIgnored' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function earnLoyalty(User $operator, int $bookingId): array
    {
        $booking = $this->db()->table('bookings')->where('id', $bookingId)->first();
        abort_if($booking === null, 404, 'Booking not found');
        $this->assertBookingAccess($operator, $booking);
        abort_unless(in_array($booking->status, ['COMPLETED', 'completed'], true), 400, 'Loyalty points are awarded after a completed ride');
        $points = $this->settingInt('loyalty_points_per_ride', 10);
        $userId = (int) $booking->customer_id;
        $already = $this->db()->table('loyalty_ledger')
            ->where('booking_id', $bookingId)
            ->where('kind', 'earn')
            ->exists();
        if ($already) {
            return $this->loyaltyFor($userId) + ['awarded' => 0, 'idempotent' => true];
        }
        $this->db()->transaction(function () use ($userId, $bookingId, $points) {
            $account = $this->db()->table('loyalty_accounts')->where('user_id', $userId)->lockForUpdate()->first();
            $balance = $account ? (int) $account->points + $points : $points;
            if ($account) {
                $this->db()->table('loyalty_accounts')->where('id', $account->id)->update([
                    'points' => $balance,
                    'updated_at' => now(),
                ]);
            } else {
                $this->db()->table('loyalty_accounts')->insert([
                    'user_id' => $userId,
                    'points' => $balance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->db()->table('loyalty_ledger')->insert([
                'user_id' => $userId,
                'booking_id' => $bookingId,
                'kind' => 'earn',
                'points' => $points,
                'created_at' => now(),
            ]);
        });

        return $this->loyaltyFor($userId) + ['awarded' => $points, 'idempotent' => false];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function previewLoyalty(User $operator, array $input): array
    {
        $this->rejectClientDiscount($input);
        $userId = (int) ($operator->nest_user_id ?: $operator->id);
        $quoted = LoyaltyEngine::quote([
            'farePaise' => (int) ($input['fare_paise'] ?? 0),
            'pointsBalance' => $this->pointsFor($userId),
            'paisePerPoint' => $this->settingInt('loyalty_paise_per_point', 100),
            'maxRedeemPaise' => $this->settingInt('loyalty_max_redeem_paise', 0),
        ]);
        $fare = max(0, (int) ($input['fare_paise'] ?? 0));

        return $quoted + [
            'farePaise' => $fare,
            'payablePaise' => max(0, $fare - $quoted['discountPaise']),
            'clientDiscountIgnored' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function quoteFor(User $operator, array $query): array
    {
        $coupon = $this->byCode((string) ($query['code'] ?? ''));
        if ($coupon === null) {
            return [
                'ok' => false,
                'reason' => 'inactive',
                'discountPaise' => 0,
                'code' => strtoupper(trim((string) ($query['code'] ?? ''))) ?: null,
                'title' => null,
            ];
        }
        $userId = (int) ($query['user_id'] ?? ($operator->nest_user_id ?: $operator->id));
        $user = $this->db()->table('users')->where('id', $userId)->first();
        $stateId = $this->nullableInt($query['state_id'] ?? $operator->state_id ?? ($user->state_id ?? null));
        $districtId = $this->nullableInt($query['district_id'] ?? $operator->district_id ?? ($user->district_id ?? null));
        $couponArr = (array) $coupon;

        return CouponEngine::quote($couponArr, [
            'farePaise' => (int) ($query['fare_paise'] ?? 0),
            'product' => $query['product'] ?? null,
            'stateId' => $stateId,
            'districtId' => $districtId,
            'role' => $user->role ?? $operator->role,
            'bookingCount' => $this->db()->table('bookings')->where('customer_id', $userId)->count(),
            'completedRides' => $this->db()->table('bookings')->where('customer_id', $userId)->whereIn('status', ['COMPLETED', 'completed'])->count(),
            'usageCount' => $this->db()->table('coupon_redemptions')->where('coupon_id', $coupon->id)->count(),
            'userUsageCount' => $this->db()->table('coupon_redemptions')->where('coupon_id', $coupon->id)->where('user_id', $userId)->count(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loyaltyAccounts(): array
    {
        return $this->db()->table('loyalty_accounts')->orderByDesc('points')->limit(100)->get()->map(fn ($row) => [
            'userId' => (int) $row->user_id,
            'points' => (int) $row->points,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function loyaltyFor(int $userId): array
    {
        return [
            'userId' => $userId,
            'points' => $this->pointsFor($userId),
            'paisePerPoint' => $this->settingInt('loyalty_paise_per_point', 100),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function rejectClientDiscount(array $input): void
    {
        foreach (['discount_paise', 'discountPaise', 'coupon_discount_paise', 'couponDiscountPaise', 'amount'] as $key) {
            if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
                throw ValidationException::withMessages([
                    $key => 'Discount amounts from the application are not accepted. Coupons are quoted on the server.',
                ]);
            }
        }
    }

    private function byCode(string $code): ?object
    {
        $trimmed = strtoupper(trim($code));
        if ($trimmed === '') {
            return null;
        }

        return $this->findRow($this->db()->table('coupons as coupons')
            ->leftJoin('states', 'states.id', '=', 'coupons.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'coupons.district_id')
            ->select('coupons.*', 'states.name as state_name', 'districts.name as district_name')
            ->where('coupons.code', $trimmed)
            ->first());
    }

    private function find(int $id): ?object
    {
        return $this->findRow($this->db()->table('coupons as coupons')
            ->leftJoin('states', 'states.id', '=', 'coupons.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'coupons.district_id')
            ->select('coupons.*', 'states.name as state_name', 'districts.name as district_name')
            ->where('coupons.id', $id)
            ->first());
    }

    private function findRow(mixed $row): ?object
    {
        return $row ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'code' => $row->code,
            'title' => $row->title,
            'subtitle' => $row->subtitle ?? null,
            'kind' => $row->kind ?? ((int) ($row->percent ?? 0) > 0 ? 'percent' : 'fixed'),
            'percent' => (int) ($row->percent ?? 0),
            'amountPaise' => (int) ($row->amount_paise ?? 0),
            'maxDiscountPaise' => (int) ($row->max_discount_paise ?? 0),
            'minFarePaise' => (int) ($row->min_fare_paise ?? 0),
            'product' => $row->product ?? null,
            'audience' => $row->audience ?? 'all',
            'usageLimit' => (int) ($row->usage_limit ?? 0),
            'userLimit' => (int) ($row->user_limit ?? 0),
            'state' => $row->state_id ? ['id' => (int) $row->state_id, 'name' => $row->state_name ?? null] : null,
            'district' => $row->district_id ? ['id' => (int) $row->district_id, 'name' => $row->district_name ?? null] : null,
            'startsOn' => $row->starts_on,
            'endsOn' => $row->ends_on,
            'active' => (bool) $row->active,
            'redemptions' => $this->db()->table('coupon_redemptions')->where('coupon_id', $row->id)->count(),
        ];
    }

    private function assertWindow(mixed $starts, mixed $ends): void
    {
        abort_if(Carbon::parse($ends)->lte(Carbon::parse($starts)), 400, 'Expiry must be after the start date');
    }

    private function assertBookingAccess(User $operator, object $booking): void
    {
        if ($operator->isPrivilegedOperator() || $operator->can('platform.admin') || $operator->can('bookings.manage')) {
            return;
        }
        $actorId = (int) ($operator->nest_user_id ?: $operator->id);
        abort_unless((int) $booking->customer_id === $actorId, 403);
    }

    private function pointsFor(int $userId): int
    {
        return (int) ($this->db()->table('loyalty_accounts')->where('user_id', $userId)->value('points') ?? 0);
    }

    private function settingInt(string $key, int $default): int
    {
        $value = $this->db()->table('system_settings')->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : (int) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
