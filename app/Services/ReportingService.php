<?php

namespace App\Services;

use App\Models\User;
use App\Platform\PaymentLifecycle;
use App\Platform\ReportCatalog;
use App\Platform\TerritoryScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReportingService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function catalog(User $operator, array $query = []): array
    {
        return [
            'from' => $this->from($query)->toDateString(),
            'to' => $this->to($query)->toDateString(),
            'formats' => ReportCatalog::FORMATS,
            'groups' => ReportCatalog::grouped($operator),
            'note' => 'Every report is scoped on the server. District, state, and fleet filters cannot be widened from the client.',
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function run(User $operator, string $key, array $query = []): array
    {
        $def = ReportCatalog::find($key);
        abort_if($def === null, 404, 'Unknown report');
        abort_unless(ReportCatalog::allows($operator, $def), 403);
        $query = TerritoryScope::constrainQuery($query, $operator);
        $rows = $this->rows($operator, $key, $query);

        return [
            'key' => $key,
            'label' => $def['label'],
            'group' => $def['group'],
            'from' => $this->from($query)->toDateString(),
            'to' => $this->to($query)->toDateString(),
            'formats' => ReportCatalog::FORMATS,
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'scope' => [
                'role' => $operator->role,
                'districtId' => $query['districtId'] ?? $operator->district_id,
                'stateId' => $query['stateId'] ?? $operator->state_id,
                'fleetId' => $query['fleetId'] ?? $operator->fleet_owner_id,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function rows(User $operator, string $key, array $query): array
    {
        return match ($key) {
            'daily_bookings' => $this->bookingsByPeriod($operator, $query, 'day'),
            'monthly_bookings' => $this->bookingsByPeriod($operator, $query, 'month'),
            'completed_rides' => $this->bookingRows($operator, $query, $this->completed()),
            'cancelled_rides' => $this->bookingRows($operator, $query, ['CANCELLED', 'cancelled']),
            'driver_performance' => $this->driverPerformance($operator, $query),
            'fleet_utilization' => $this->fleetUtilization($operator, $query),
            'district_performance' => $this->territoryPerformance($operator, $query, 'district'),
            'state_performance' => $this->territoryPerformance($operator, $query, 'state'),
            'gross_revenue' => $this->revenueRows($operator, $query, false),
            'net_revenue' => $this->revenueRows($operator, $query, true),
            'driver_earnings' => $this->ledgerRows($operator, $query, ['DRIVER']),
            'fleet_earnings' => $this->ledgerRows($operator, $query, ['FLEET_OWNER']),
            'franchise_commission' => $this->ledgerRows($operator, $query, ['FRANCHISE', 'DISTRICT_HEAD']),
            'platform_commission' => $this->ledgerRows($operator, $query, ['PLATFORM']),
            'payment_methods' => $this->paymentMethods($operator, $query),
            'refunds' => $this->refunds($operator, $query),
            'wallets' => $this->walletRows($operator, $query),
            'new_users' => $this->newUsers($operator, $query),
            'active_users' => $this->activeUsers($operator, $query),
            'repeat_customers' => $this->repeatCustomers($operator, $query),
            'ratings' => $this->ratings($operator, $query),
            'complaints' => $this->complaints($operator, $query),
            'parcel_orders' => $this->parcelRows($operator, $query),
            'parcel_success' => $this->parcelRows($operator, $query, ['DELIVERED', 'delivered', 'SUCCESS', 'COMPLETED']),
            'parcel_failed' => $this->parcelRows($operator, $query, ['FAILED', 'failed', 'CANCELLED', 'cancelled']),
            'parcel_revenue' => $this->parcelRevenue($operator, $query),
            'parcel_driver_performance' => $this->parcelDrivers($operator, $query),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function bookingsByPeriod(User $operator, array $query, string $grain): array
    {
        $expr = $grain === 'month' ? $this->monthExpr('bookings.created_at') : $this->dayExpr('bookings.created_at');
        $q = $this->bookings($operator);
        $this->inRange($q, 'bookings.created_at', $query);

        return $q->selectRaw($expr.' as period')
            ->selectRaw('count(*) as bookings')
            ->selectRaw("sum(case when bookings.status in ('COMPLETED','completed') then 1 else 0 end) as completed")
            ->selectRaw("sum(case when bookings.status in ('CANCELLED','cancelled') then 1 else 0 end) as cancelled")
            ->selectRaw('coalesce(sum(bookings.quote_paise),0) as quotePaise')
            ->groupByRaw($expr)
            ->orderByRaw($expr)
            ->get()
            ->map(fn ($row) => [
                'period' => (string) $row->period,
                'bookings' => (int) $row->bookings,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'quotePaise' => (int) $row->quotePaise,
                'quoteRupees' => ((int) $row->quotePaise) / 100,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $statuses
     * @return list<array<string, mixed>>
     */
    private function bookingRows(User $operator, array $query, array $statuses): array
    {
        $q = $this->bookings($operator)->whereIn('bookings.status', $statuses);
        $this->inRange($q, 'bookings.created_at', $query);

        return $q->orderByDesc('bookings.id')->limit(500)->get()->map(fn ($row) => [
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'product' => $row->product,
            'districtId' => $row->district_id,
            'quotePaise' => (int) ($row->quote_paise ?? 0),
            'quoteRupees' => ((int) ($row->quote_paise ?? 0)) / 100,
            'createdAt' => (string) $row->created_at,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function driverPerformance(User $operator, array $query): array
    {
        $q = $this->bookings($operator)->whereNotNull('bookings.driver_id');
        $this->inRange($q, 'bookings.created_at', $query);
        $rows = $q->leftJoin('drivers', 'drivers.id', '=', 'bookings.driver_id')
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->selectRaw('bookings.driver_id as driverId')
            ->selectRaw('users.name as driverName')
            ->selectRaw('count(*) as trips')
            ->selectRaw("sum(case when bookings.status in ('COMPLETED','completed') then 1 else 0 end) as completed")
            ->selectRaw("sum(case when bookings.status in ('CANCELLED','cancelled') then 1 else 0 end) as cancelled")
            ->selectRaw('coalesce(sum(bookings.quote_paise),0) as quotePaise')
            ->groupBy('bookings.driver_id', 'users.name')
            ->orderByDesc('completed')
            ->limit(200)
            ->get();

        return $rows->map(function ($row) {
            $avg = null;
            if (Schema::connection('platform')->hasTable('booking_ratings')) {
                $avg = DB::connection('platform')->table('booking_ratings')
                    ->join('bookings', 'bookings.id', '=', 'booking_ratings.booking_id')
                    ->where('bookings.driver_id', $row->driverId)
                    ->avg('booking_ratings.stars');
            }

            return [
                'driverId' => (int) $row->driverId,
                'driverName' => $row->driverName,
                'trips' => (int) $row->trips,
                'completed' => (int) $row->completed,
                'cancelled' => (int) $row->cancelled,
                'quotePaise' => (int) $row->quotePaise,
                'quoteRupees' => ((int) $row->quotePaise) / 100,
                'avgStars' => $avg !== null ? round((float) $avg, 2) : null,
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function fleetUtilization(User $operator, array $query): array
    {
        $vehicles = $this->q('vehicles');
        TerritoryScope::applyVehicles($vehicles, $operator);
        $ids = $vehicles->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $q = $this->bookings($operator)->whereIn('bookings.vehicle_id', $ids);
        $this->inRange($q, 'bookings.created_at', $query);

        return $q->leftJoin('vehicles', 'vehicles.id', '=', 'bookings.vehicle_id')
            ->selectRaw('vehicles.id as vehicleId')
            ->selectRaw('vehicles.registration_no as registrationNo')
            ->selectRaw('count(*) as trips')
            ->selectRaw("sum(case when bookings.status in ('COMPLETED','completed') then 1 else 0 end) as completed")
            ->selectRaw('coalesce(sum(bookings.quote_paise),0) as quotePaise')
            ->groupBy('vehicles.id', 'vehicles.registration_no')
            ->orderByDesc('trips')
            ->limit(200)
            ->get()
            ->map(fn ($row) => [
                'vehicleId' => (int) $row->vehicleId,
                'registrationNo' => $row->registrationNo,
                'trips' => (int) $row->trips,
                'completed' => (int) $row->completed,
                'quotePaise' => (int) $row->quotePaise,
                'quoteRupees' => ((int) $row->quotePaise) / 100,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function territoryPerformance(User $operator, array $query, string $grain): array
    {
        $q = $this->bookings($operator)
            ->leftJoin('districts', 'districts.id', '=', 'bookings.district_id')
            ->leftJoin('states', 'states.id', '=', 'districts.state_id');
        $this->inRange($q, 'bookings.created_at', $query);
        if ($grain === 'state') {
            $q->selectRaw('states.id as territoryId')
                ->selectRaw('states.name as territory')
                ->groupBy('states.id', 'states.name');
        } else {
            $q->selectRaw('districts.id as territoryId')
                ->selectRaw('districts.name as territory')
                ->groupBy('districts.id', 'districts.name');
        }

        return $q->selectRaw('count(*) as bookings')
            ->selectRaw("sum(case when bookings.status in ('COMPLETED','completed') then 1 else 0 end) as completed")
            ->selectRaw('coalesce(sum(bookings.quote_paise),0) as quotePaise')
            ->orderByDesc('quotePaise')
            ->get()
            ->map(fn ($row) => [
                'territoryId' => $row->territoryId ? (int) $row->territoryId : null,
                'territory' => $row->territory ?: 'Unassigned',
                'bookings' => (int) $row->bookings,
                'completed' => (int) $row->completed,
                'quotePaise' => (int) $row->quotePaise,
                'quoteRupees' => ((int) $row->quotePaise) / 100,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function revenueRows(User $operator, array $query, bool $net): array
    {
        $q = $this->bookings($operator)->whereIn('bookings.status', $this->completed());
        $this->inRange($q, 'bookings.created_at', $query);
        $gross = (int) $q->sum('bookings.quote_paise');
        $refunds = 0;
        $payments = $this->payments($operator);
        $this->inRange($payments, 'payments.created_at', $query);
        $refunds = (int) (clone $payments)->where(function ($inner) {
            $inner->whereIn('payments.kind', ['refund', 'partial_refund'])
                ->orWhereIn('payments.status', PaymentLifecycle::REFUND_STEPS);
        })->sum('payments.amount_paise');
        $netPaise = max(0, $gross - $refunds);

        return [[
            'grossPaise' => $gross,
            'grossRupees' => $gross / 100,
            'refundPaise' => $refunds,
            'refundRupees' => $refunds / 100,
            'netPaise' => $net ? $netPaise : $gross,
            'netRupees' => ($net ? $netPaise : $gross) / 100,
        ]];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>  $accounts
     * @return list<array<string, mixed>>
     */
    private function ledgerRows(User $operator, array $query, array $accounts): array
    {
        if (! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return [];
        }
        $q = $this->ledger($operator)->where('wallet_ledger.direction', 'CREDIT');
        $q->where(function ($inner) use ($accounts) {
            $inner->whereIn('wallet_ledger.account', $accounts);
            if (Schema::connection('platform')->hasColumn('wallet_ledger', 'owner_type')) {
                $inner->orWhereIn('wallet_ledger.owner_type', $accounts);
            }
        });
        $this->inRange($q, 'wallet_ledger.created_at', $query);

        return $q->orderByDesc('wallet_ledger.id')->limit(500)->get()->map(fn ($row) => [
            'ref' => $row->public_ref ?? null,
            'account' => $row->account ?? $row->owner_type ?? null,
            'amountPaise' => (int) $row->amount_paise,
            'amountRupees' => ((int) $row->amount_paise) / 100,
            'commissionPaise' => (int) ($row->commission_paise ?? 0),
            'bookingId' => $row->booking_id ?? null,
            'createdAt' => (string) ($row->created_at ?? ''),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function paymentMethods(User $operator, array $query): array
    {
        $q = $this->payments($operator)->whereIn('payments.status', PaymentLifecycle::SUCCESS_ALIASES);
        $this->inRange($q, 'payments.created_at', $query);

        return $q->select('payments.method')
            ->selectRaw('count(*) as payments')
            ->selectRaw('coalesce(sum(payments.amount_paise),0) as amountPaise')
            ->groupBy('payments.method')
            ->orderByDesc('amountPaise')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'payments' => (int) $row->payments,
                'amountPaise' => (int) $row->amountPaise,
                'amountRupees' => ((int) $row->amountPaise) / 100,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function refunds(User $operator, array $query): array
    {
        $q = $this->payments($operator)->where(function ($inner) {
            $inner->whereIn('payments.kind', ['refund', 'partial_refund'])
                ->orWhereIn('payments.status', PaymentLifecycle::REFUND_STEPS);
        });
        $this->inRange($q, 'payments.created_at', $query);

        return $q->orderByDesc('payments.id')->limit(500)->get()->map(fn ($row) => [
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'method' => $row->method,
            'amountPaise' => (int) $row->amount_paise,
            'amountRupees' => ((int) $row->amount_paise) / 100,
            'bookingId' => $row->booking_id,
            'createdAt' => (string) ($row->created_at ?? ''),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function walletRows(User $operator, array $query): array
    {
        $q = $this->ledger($operator);
        $this->inRange($q, 'wallet_ledger.created_at', $query);

        return $q->orderByDesc('wallet_ledger.id')->limit(500)->get()->map(fn ($row) => [
            'ref' => $row->public_ref ?? null,
            'account' => $row->account ?? $row->owner_type ?? null,
            'direction' => $row->direction,
            'kind' => $row->kind ?? null,
            'amountPaise' => (int) $row->amount_paise,
            'amountRupees' => ((int) $row->amount_paise) / 100,
            'createdAt' => (string) ($row->created_at ?? ''),
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function newUsers(User $operator, array $query): array
    {
        $q = $this->q('users')->where('users.role', 'CUSTOMER');
        TerritoryScope::applyUsers($q, $operator);
        $this->inRange($q, 'users.created_at', $query);

        return $q->orderByDesc('users.id')->limit(500)->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'email' => $row->email,
            'districtId' => $row->district_id ?? null,
            'createdAt' => (string) $row->created_at,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function activeUsers(User $operator, array $query): array
    {
        $q = $this->bookings($operator);
        $this->inRange($q, 'bookings.created_at', $query);

        return $q->join('users', 'users.id', '=', 'bookings.customer_id')
            ->selectRaw('users.id as customerId')
            ->selectRaw('users.name as name')
            ->selectRaw('count(*) as bookings')
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('bookings')
            ->limit(500)
            ->get()
            ->map(fn ($row) => [
                'customerId' => (int) $row->customerId,
                'name' => $row->name,
                'bookings' => (int) $row->bookings,
            ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function repeatCustomers(User $operator, array $query): array
    {
        return array_values(array_filter($this->activeUsers($operator, $query), fn (array $row) => $row['bookings'] >= 2));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function ratings(User $operator, array $query): array
    {
        if (! Schema::connection('platform')->hasTable('booking_ratings')) {
            return [];
        }
        $q = $this->q('booking_ratings')
            ->join('bookings', 'bookings.id', '=', 'booking_ratings.booking_id');
        TerritoryScope::applyBookings($q, $operator, 'bookings');
        $col = Schema::connection('platform')->hasColumn('booking_ratings', 'created_at')
            ? 'booking_ratings.created_at'
            : 'bookings.created_at';
        $this->inRange($q, $col, $query);

        return $q->orderByDesc('booking_ratings.id')->limit(500)->get()->map(fn ($row) => [
            'bookingId' => (int) $row->booking_id,
            'stars' => (int) $row->stars,
            'fromRole' => $row->from_role,
            'comment' => $row->comment,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function complaints(User $operator, array $query): array
    {
        $q = $this->q('support_tickets');
        if (Schema::connection('platform')->hasColumn('support_tickets', 'district_id')) {
            TerritoryScope::applySafetyIncidents($q, $operator, 'support_tickets');
        }
        $this->inRange($q, 'support_tickets.created_at', $query);

        return $q->orderByDesc('support_tickets.id')->limit(500)->get()->map(fn ($row) => [
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'subject' => $row->subject,
            'category' => $row->category ?? null,
            'districtId' => $row->district_id ?? null,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  list<string>|null  $statuses
     * @return list<array<string, mixed>>
     */
    private function parcelRows(User $operator, array $query, ?array $statuses = null): array
    {
        $q = $this->parcels($operator);
        if ($statuses) {
            $q->whereIn('parcel_shipments.status', $statuses);
        }
        if (Schema::connection('platform')->hasColumn('parcel_shipments', 'created_at')) {
            $this->inRange($q, 'parcel_shipments.created_at', $query);
        }

        return $q->orderByDesc('parcel_shipments.id')->limit(500)->get()->map(fn ($row) => [
            'publicRef' => $row->public_ref,
            'status' => $row->status,
            'pickupText' => $row->pickup_text ?? null,
            'dropText' => $row->drop_text ?? null,
            'quotePaise' => (int) ($row->quote_paise ?? 0),
            'quoteRupees' => ((int) ($row->quote_paise ?? 0)) / 100,
        ])->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function parcelRevenue(User $operator, array $query): array
    {
        $rows = $this->parcelRows($operator, $query);
        $total = array_sum(array_column($rows, 'quotePaise'));

        return [[
            'orders' => count($rows),
            'quotePaise' => $total,
            'quoteRupees' => $total / 100,
        ]];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    private function parcelDrivers(User $operator, array $query): array
    {
        $q = $this->parcels($operator)->whereNotNull('parcel_shipments.driver_id');
        if (Schema::connection('platform')->hasColumn('parcel_shipments', 'created_at')) {
            $this->inRange($q, 'parcel_shipments.created_at', $query);
        }

        return $q->leftJoin('drivers', 'drivers.id', '=', 'parcel_shipments.driver_id')
            ->leftJoin('users', 'users.id', '=', 'drivers.user_id')
            ->selectRaw('parcel_shipments.driver_id as driverId')
            ->selectRaw('users.name as driverName')
            ->selectRaw('count(*) as orders')
            ->selectRaw("sum(case when parcel_shipments.status in ('DELIVERED','delivered','SUCCESS','COMPLETED') then 1 else 0 end) as delivered")
            ->selectRaw("sum(case when parcel_shipments.status in ('FAILED','failed','CANCELLED','cancelled') then 1 else 0 end) as failed")
            ->groupBy('parcel_shipments.driver_id', 'users.name')
            ->orderByDesc('delivered')
            ->get()
            ->map(fn ($row) => [
                'driverId' => (int) $row->driverId,
                'driverName' => $row->driverName,
                'orders' => (int) $row->orders,
                'delivered' => (int) $row->delivered,
                'failed' => (int) $row->failed,
            ])->all();
    }

    /**
     * @return list<string>
     */
    private function completed(): array
    {
        return ['COMPLETED', 'completed'];
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function from(array $query): Carbon
    {
        return ! empty($query['from']) ? Carbon::parse((string) $query['from'])->startOfDay() : now()->subDays(30)->startOfDay();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function to(array $query): Carbon
    {
        return ! empty($query['to']) ? Carbon::parse((string) $query['to'])->endOfDay() : now()->endOfDay();
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function inRange(Builder $q, string $column, array $query): void
    {
        $q->whereBetween($column, [$this->from($query), $this->to($query)]);
    }

    private function bookings(User $operator): Builder
    {
        $q = $this->q('bookings');
        TerritoryScope::applyBookings($q, $operator);

        return $q;
    }

    private function payments(User $operator): Builder
    {
        $q = $this->q('payments');
        if (! TerritoryScope::isUnrestricted($operator)) {
            $q->where(function ($inner) use ($operator) {
                $inner->whereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('bookings')->whereColumn('bookings.id', 'payments.booking_id');
                    TerritoryScope::applyBookings($sub, $operator, 'bookings');
                });
            });
        }

        return $q;
    }

    private function parcels(User $operator): Builder
    {
        $q = $this->q('parcel_shipments');
        TerritoryScope::applyParcels($q, $operator);

        return $q;
    }

    private function ledger(User $operator): Builder
    {
        $q = $this->q('wallet_ledger');
        if (! TerritoryScope::isUnrestricted($operator)) {
            $q->where(function ($inner) use ($operator) {
                $inner->whereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('bookings')->whereColumn('bookings.id', 'wallet_ledger.booking_id');
                    TerritoryScope::applyBookings($sub, $operator, 'bookings');
                })->orWhereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('wallets')
                        ->whereColumn('wallets.id', 'wallet_ledger.wallet_id')
                        ->whereExists(function ($users) use ($operator) {
                            $users->selectRaw('1')->from('users')->whereColumn('users.id', 'wallets.owner_user_id');
                            TerritoryScope::applyUsers($users, $operator, 'users');
                        });
                });
            });
        }

        return $q;
    }

    private function dayExpr(string $column): string
    {
        return $this->sqlite() ? "strftime('%Y-%m-%d', {$column})" : "DATE({$column})";
    }

    private function monthExpr(string $column): string
    {
        return $this->sqlite() ? "strftime('%Y-%m', {$column})" : "DATE_FORMAT({$column}, '%Y-%m')";
    }

    private function sqlite(): bool
    {
        return DB::connection('platform')->getDriverName() === 'sqlite';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function totals(array $rows): array
    {
        if ($rows === []) {
            return ['rows' => 0];
        }
        $totals = ['rows' => count($rows)];
        foreach (array_keys($rows[0]) as $key) {
            if (str_ends_with($key, 'Paise') || in_array($key, ['bookings', 'completed', 'cancelled', 'trips', 'payments', 'orders', 'delivered', 'failed'], true)) {
                $totals[$key] = array_sum(array_map(fn ($row) => (int) ($row[$key] ?? 0), $rows));
            }
        }

        return $totals;
    }

    private function q(string $table): Builder
    {
        return DB::connection('platform')->table($table);
    }
}
