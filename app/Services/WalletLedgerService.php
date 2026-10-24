<?php

namespace App\Services;

use App\Models\User;
use App\Platform\CommissionEngine;
use App\Platform\FareCommissionSplit;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WalletLedgerService
{
    public const ACCOUNTS = [
        'CUSTOMER',
        'DRIVER',
        'FLEET_OWNER',
        'FRANCHISE',
        'DISTRICT_HEAD',
        'CORPORATE',
        'PLATFORM',
    ];

    /**
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        $rule = $this->activeRule();

        return CommissionEngine::serializePolicy($rule) + [
            'id' => $rule->id ?? null,
            'name' => $rule->name ?? 'default',
            'active' => (bool) ($rule->active ?? true),
            'configured' => $rule !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePolicy(User $operator, array $data): array
    {
        abort_unless($operator->can('platform.admin'), 403);
        abort_unless(isset($data['percent']) && is_numeric($data['percent']), 422, 'Commission percent must be set on the rule.');

        $payload = [
            'name' => 'default',
            'percent' => $data['percent'],
            'active' => 1,
            'on_base_fare' => ! empty($data['on_base_fare']) || ! empty($data['on_complete']),
            'on_gst' => ! empty($data['on_gst']),
            'on_toll' => ! empty($data['on_toll']),
            'on_parking' => ! empty($data['on_parking']),
            'on_waiting' => ! empty($data['on_waiting']),
            'on_discount' => ! empty($data['on_discount']),
            'on_other' => ! empty($data['on_other']),
        ];
        if ($this->hasColumn('commission_rules', 'on_complete')) {
            $payload['on_complete'] = ! empty($data['on_complete']);
        }
        if ($this->hasColumn('commission_rules', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        $existing = $this->db()->table('commission_rules')->where('name', 'default')->first();
        if ($existing) {
            $this->db()->table('commission_rules')->where('id', $existing->id)->update($payload);
        } else {
            if ($this->hasColumn('commission_rules', 'created_at')) {
                $payload['created_at'] = now();
            }
            $this->db()->table('commission_rules')->insert($payload);
        }

        return $this->policy();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function wallets(?User $operator, array $query = []): array
    {
        abort_unless($operator?->can('wallet.view'), 403);
        $this->ensureSchema();
        $q = $this->scopedWallets($operator)
            ->leftJoin('users', 'users.id', '=', 'wallets.owner_user_id')
            ->select('wallets.*', 'users.name as owner_name', 'users.email as owner_email', 'users.role as owner_role');
        $type = $query['owner_type'] ?? $query['ownerType'] ?? null;
        if (is_string($type) && $type !== '') {
            $q->where('wallets.owner_type', $type);
        } elseif ($operator?->can('platform.admin') || $operator?->isPrivilegedOperator()) {
            $q->where('wallets.owner_type', '!=', 'PLATFORM');
        }

        return $q->orderByDesc('wallets.id')->limit(200)->get()->map(fn ($row) => $this->presentWallet($row))->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function platformEarnings(?User $operator): ?array
    {
        if (! $operator || (! $operator->can('platform.admin') && ! $operator->isPrivilegedOperator())) {
            return null;
        }
        $this->ensureSchema();
        $q = $this->db()->table('wallets')->where('owner_type', 'PLATFORM')->orderByDesc('id');
        $row = $q->first();
        if (! $row) {
            try {
                $platformUserId = $this->platformWalletUserId();
                $insert = $this->filterRow('wallets', [
                    'owner_type' => 'PLATFORM',
                    'owner_user_id' => $platformUserId,
                    'balance_paise' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($platformUserId || ! $this->hasColumn('wallets', 'owner_user_id')) {
                    $this->db()->table('wallets')->insert($insert);
                    $row = $this->db()->table('wallets')->where('owner_type', 'PLATFORM')->orderByDesc('id')->first();
                }
            } catch (QueryException) {
                $row = null;
            }
        }
        $today = 0;
        $month = 0;
        try {
            if (Schema::connection('platform')->hasTable('wallet_ledger')) {
                $base = $this->db()->table('wallet_ledger')->where('direction', 'CREDIT');
                if (Schema::connection('platform')->hasColumn('wallet_ledger', 'account')) {
                    $base->where('account', 'PLATFORM');
                } elseif (Schema::connection('platform')->hasColumn('wallet_ledger', 'owner_type')) {
                    $base->where('owner_type', 'PLATFORM');
                }
                if (Schema::connection('platform')->hasColumn('wallet_ledger', 'kind')) {
                    $base->whereIn('kind', ['platform_commission', 'commission', 'trip', 'settlement']);
                }
                $today = (int) (clone $base)->whereDate('created_at', now()->toDateString())->sum('amount_paise');
                $month = (int) (clone $base)->where('created_at', '>=', now()->startOfMonth())->sum('amount_paise');
            }
        } catch (QueryException) {
            $today = 0;
            $month = 0;
        }

        return [
            'walletId' => $row->id ?? null,
            'balanceRupees' => ((int) ($row->balance_paise ?? 0)) / 100,
            'todayRupees' => $today / 100,
            'monthRupees' => $month / 100,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function wallet(User $operator, int $id): array
    {
        abort_unless($operator->can('wallet.view'), 403);
        $row = $this->scopedWallets($operator)
            ->leftJoin('users', 'users.id', '=', 'wallets.owner_user_id')
            ->select('wallets.*', 'users.name as owner_name', 'users.email as owner_email', 'users.role as owner_role')
            ->where('wallets.id', $id)
            ->first();
        abort_unless($row, 404, 'Wallet not found');

        return $this->presentWallet($row) + [
            'ledger' => $this->ledgerQuery($operator)->where('wallet_ledger.wallet_id', $id)->orderByDesc('wallet_ledger.id')->limit(80)->get()->map(fn ($entry) => $this->presentLedger($entry))->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function ledger(User $operator, array $query = []): array
    {
        abort_unless($operator->can('wallet.view'), 403);
        $q = $this->ledgerQuery($operator);
        if (! empty($query['wallet_id'])) {
            $q->where('wallet_ledger.wallet_id', (int) $query['wallet_id']);
        }
        if (! empty($query['booking_id'])) {
            $q->where('wallet_ledger.booking_id', (int) $query['booking_id']);
        }
        if (! empty($query['user_id'])) {
            $q->where(function ($inner) use ($query) {
                $inner->where('wallets.owner_user_id', (int) $query['user_id']);
                if ($this->hasColumn('wallet_ledger', 'owner_user_id')) {
                    $inner->orWhere('wallet_ledger.owner_user_id', (int) $query['user_id']);
                }
            });
        }
        if (! empty($query['direction'])) {
            $q->where('wallet_ledger.direction', $query['direction']);
        }
        if (! empty($query['kind'])) {
            $q->where('wallet_ledger.kind', $query['kind']);
        }
        if (! empty($query['account'])) {
            $q->where('wallet_ledger.account', $query['account']);
        }
        if (! empty($query['status'])) {
            $q->where('wallet_ledger.status', $query['status']);
        }
        if (! empty($query['q'])) {
            $term = '%'.$query['q'].'%';
            $q->where(function ($inner) use ($term) {
                $inner->where('wallet_ledger.public_ref', 'like', $term)
                    ->orWhere('wallet_ledger.note', 'like', $term);
                if ($this->hasColumn('wallet_ledger', 'payment_ref')) {
                    $inner->orWhere('wallet_ledger.payment_ref', 'like', $term);
                }
            });
        }

        return $q->orderByDesc('wallet_ledger.id')->limit(80)->get()->map(fn ($row) => $this->presentLedger($row))->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    /**
     * Users an admin can pick when adding wallet money.
     *
     * @return list<array<string, mixed>>
     */
    public function creditCandidates(User $operator, string $term = ''): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $q = $this->db()->table('users')->select('users.id', 'users.name', 'users.phone', 'users.email', 'users.role');
        TerritoryScope::applyUsers($q, $operator, 'users');
        $term = trim($term);
        if ($term !== '') {
            $q->where(function ($inner) use ($term) {
                $inner->where('users.name', 'like', '%'.$term.'%')
                    ->orWhere('users.phone', 'like', '%'.$term.'%')
                    ->orWhere('users.email', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $inner->orWhere('users.id', (int) $term);
                }
            });
        }
        $rows = $q->orderBy('users.name')->orderByDesc('users.id')->limit(250)->get();
        if ($rows->isEmpty()) {
            return [];
        }
        $wallets = $this->db()->table('wallets')
            ->whereIn('owner_user_id', $rows->pluck('id'))
            ->get()
            ->groupBy('owner_user_id');

        return $rows->map(function ($row) use ($wallets) {
            $account = $this->walletAccountForRole((string) $row->role);
            $wallet = collect($wallets->get($row->id, []))->first(fn ($item) => (string) $item->owner_type === $account);
            $paise = (int) ($wallet->balance_paise ?? 0);

            return [
                'id' => (int) $row->id,
                'name' => (string) $row->name,
                'phone' => (string) ($row->phone ?? ''),
                'email' => (string) ($row->email ?? ''),
                'role' => (string) $row->role,
                'account' => $account,
                'balanceRupees' => $paise / 100,
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function creditUser(User $operator, int $userId, int $amountPaise, ?string $note = null): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        abort_unless($amountPaise >= 100, 422, 'Enter at least ₹1.');
        $visible = $this->db()->table('users')->where('users.id', $userId);
        TerritoryScope::applyUsers($visible, $operator, 'users');
        $user = $visible->first();
        abort_unless($user, 404, 'User not found.');

        return $this->post($operator, [
            'owner_type' => $this->walletAccountForRole((string) $user->role),
            'owner_user_id' => (int) $user->id,
            'direction' => 'CREDIT',
            'amount_paise' => $amountPaise,
            'kind' => 'admin_credit',
            'note' => trim((string) $note) !== '' ? trim((string) $note) : 'Added by admin',
        ]);
    }

    public function post(User $operator, array $input): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $ownerType = strtoupper((string) ($input['owner_type'] ?? ''));
        abort_unless(in_array($ownerType, self::ACCOUNTS, true), 422, 'Unknown wallet account.');

        $posted = $this->postLedger([
            'ownerType' => $ownerType,
            'ownerUserId' => (int) $input['owner_user_id'],
            'direction' => strtoupper((string) $input['direction']),
            'amountPaise' => (int) $input['amount_paise'],
            'commissionPaise' => (int) ($input['commission_paise'] ?? 0),
            'grossPaise' => isset($input['gross_paise']) ? (int) $input['gross_paise'] : null,
            'bookingId' => isset($input['booking_id']) ? (int) $input['booking_id'] : null,
            'kind' => (string) ($input['kind'] ?? 'adjustment'),
            'status' => (string) ($input['status'] ?? 'posted'),
            'note' => $input['note'] ?? 'Manual ledger post',
            'paymentRef' => $input['payment_ref'] ?? null,
        ]);

        return $this->presentLedger((object) $posted['ledger']);
    }

    /**
     * @return array<string, mixed>
     */
    public function settleBooking(User $operator, int $bookingId): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('bookings.manage') || $operator->can('platform.admin'), 403);
        $visible = $this->db()->table('bookings')->where('id', $bookingId);
        TerritoryScope::applyBookings($visible, $operator);
        $booking = $visible->first();
        abort_unless($booking, 404, 'Booking not found');

        $this->db()->transaction(function () use ($booking) {
            $this->settleCompletedTrip($booking);
        });

        return [
            'ok' => true,
            'bookingId' => (string) $bookingId,
            'ledger' => $this->ledger($operator, ['booking_id' => $bookingId]),
            'settlement' => $this->lastSettlementForBooking((int) $booking->id),
        ];
    }

    public function settleCompletedTrip(object $row): void
    {
        if (empty($row->driver_id)) {
            return;
        }
        $existing = $this->db()->table('wallet_ledger')
            ->where('booking_id', $row->id)
            ->where('direction', 'CREDIT')
            ->where('kind', 'trip')
            ->where('account', 'DRIVER')
            ->exists();
        if ($existing) {
            return;
        }

        $rule = $this->activeRule();
        abort_unless($rule, 422, 'Commission rule is not configured.');
        $totalPaise = (int) ($row->quote_paise ?? 0);
        $fromQuote = CommissionEngine::settleFromQuoteSnapshot($row->quote_snapshot ?? null, $totalPaise);
        $hasStoredCommission = ((int) ($fromQuote['commissionPaise'] ?? 0) > 0)
            || (is_numeric($fromQuote['percent'] ?? null) && (int) ($fromQuote['eligiblePaise'] ?? 0) > 0);
        if ($hasStoredCommission) {
            $settled = $fromQuote;
            if (($settled['percent'] ?? null) === null) {
                $settled = CommissionEngine::settle($fromQuote['buckets'] ?? CommissionEngine::emptyBuckets(), $rule, $totalPaise);
            }
        } else {
            $buckets = ! empty($fromQuote['fromBreakdown'])
                ? ($fromQuote['buckets'] ?? CommissionEngine::emptyBuckets())
                : $this->bucketsForTotal($rule, $totalPaise);
            $settled = CommissionEngine::settle($buckets, $rule, $totalPaise ?: (int) ($fromQuote['netPaise'] ?? 0));
        }

        $driver = $this->db()->table('drivers')->where('id', $row->driver_id)->first();
        if (! $driver) {
            return;
        }
        $fleetUserId = null;
        $fleetOwnerId = 0;
        if (! empty($row->vehicle_id)) {
            $vehicle = $this->db()->table('vehicles')->where('id', $row->vehicle_id)->first();
            if ($vehicle?->fleet_owner_id) {
                $fleetOwnerId = (int) $vehicle->fleet_owner_id;
                $fleetUserId = $this->db()->table('fleet_owners')->where('id', $vehicle->fleet_owner_id)->value('user_id');
            }
        }
        if (! $fleetUserId && ! empty($driver->fleet_owner_id)) {
            $fleetOwnerId = (int) $driver->fleet_owner_id;
            $fleetUserId = $this->db()->table('fleet_owners')->where('id', $driver->fleet_owner_id)->value('user_id');
        }

        $fleetPct = $this->fleetCutPercent($fleetOwnerId);
        $farePaise = $totalPaise ?: ((int) ($fromQuote['netPaise'] ?? 0) + (int) ($settled['commissionPaise'] ?? 0));
        $split = FareCommissionSplit::split($farePaise, (int) $settled['commissionPaise'], $fleetPct);
        $districtUserId = 0;
        if (! empty($row->district_id)) {
            $districtUserId = (int) ($this->db()->table('users')
                ->where('role', OperatorRole::DISTRICT_HEAD)
                ->where('district_id', (int) $row->district_id)
                ->where('status', 'ACTIVE')
                ->orderBy('id')
                ->value('id') ?? 0);
        }

        $payment = $this->db()->table('payments')
            ->where('booking_id', $row->id)
            ->whereIn('status', ['captured', 'paid', 'success'])
            ->orderByDesc('id')
            ->first();
        $paymentRef = $payment->public_ref ?? null;
        $walletPay = $payment && strtolower((string) $payment->method) === 'wallet' && $payment->status !== 'captured';
        $farePaise = $totalPaise ?: ((int) $settled['netPaise'] + (int) $settled['commissionPaise']);

        if ($walletPay && empty($row->corporate_account_id) && $farePaise > 0) {
            $this->postLedger([
                'ownerType' => 'CUSTOMER',
                'ownerUserId' => (int) $row->customer_id,
                'direction' => 'DEBIT',
                'amountPaise' => $farePaise,
                'commissionPaise' => (int) $settled['commissionPaise'],
                'grossPaise' => $farePaise,
                'bookingId' => (int) $row->id,
                'kind' => 'trip',
                'note' => 'Trip payment from customer wallet',
                'paymentRef' => $paymentRef,
            ]);
        }

        if ((int) $settled['netPaise'] > 0) {
            $this->postLedger([
                'ownerType' => 'DRIVER',
                'ownerUserId' => (int) $driver->user_id,
                'direction' => 'CREDIT',
                'amountPaise' => (int) $settled['netPaise'],
                'commissionPaise' => (int) $settled['commissionPaise'],
                'grossPaise' => (int) $settled['netPaise'] + (int) $settled['commissionPaise'],
                'bookingId' => (int) $row->id,
                'kind' => 'trip',
                'note' => 'Trip earnings after configured commission',
                'paymentRef' => $paymentRef,
            ]);
        }

        if ($fleetUserId && $split['fleetPaise'] > 0) {
            $this->postLedger([
                'ownerType' => 'FLEET_OWNER',
                'ownerUserId' => (int) $fleetUserId,
                'direction' => 'CREDIT',
                'amountPaise' => $split['fleetPaise'],
                'commissionPaise' => $split['fleetPaise'],
                'grossPaise' => (int) $settled['commissionPaise'],
                'bookingId' => (int) $row->id,
                'kind' => 'fleet_commission',
                'note' => 'Fleet cut from the district 4% share',
                'paymentRef' => $paymentRef,
            ]);
        }

        if ($districtUserId > 0 && $split['districtHeadPaise'] > 0) {
            $this->postLedger([
                'ownerType' => 'DISTRICT_HEAD',
                'ownerUserId' => $districtUserId,
                'direction' => 'CREDIT',
                'amountPaise' => $split['districtHeadPaise'],
                'commissionPaise' => $split['districtHeadPaise'],
                'grossPaise' => (int) $settled['commissionPaise'],
                'bookingId' => (int) $row->id,
                'kind' => 'district_head_commission',
                'note' => 'District Head net 4% after fleet cut',
                'paymentRef' => $paymentRef,
            ]);
        }

        if ($split['platformPaise'] > 0) {
            $platformUserId = $this->platformWalletUserId();
            if ($platformUserId) {
                $this->postLedger([
                    'ownerType' => 'PLATFORM',
                    'ownerUserId' => $platformUserId,
                    'direction' => 'CREDIT',
                    'amountPaise' => $split['platformPaise'],
                    'commissionPaise' => $split['platformPaise'],
                    'grossPaise' => (int) $settled['commissionPaise'],
                    'bookingId' => (int) $row->id,
                    'kind' => 'platform_commission',
                    'note' => 'Platform 6% of fare',
                    'paymentRef' => $paymentRef,
                ]);
            }
        }
        $this->persistCommissionSplit($row, $split);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{wallet: object, ledger: array<string, mixed>}
     */
    public function postLedger(array $input): array
    {
        $amount = (int) $input['amountPaise'];
        abort_unless($amount > 0, 400, 'Ledger amount must be greater than zero');
        $direction = strtoupper((string) $input['direction']);
        abort_unless(in_array($direction, ['CREDIT', 'DEBIT'], true), 422, 'Direction must be CREDIT or DEBIT');

        $this->ensureSchema();
        $posted = $this->db()->transaction(function () use ($input, $amount, $direction) {
            $wallet = $this->ensureWallet((int) $input['ownerUserId'], (string) $input['ownerType'], true);
            abort_unless($wallet, 422, 'Could not create a wallet for this user.');
            $before = (int) $wallet->balance_paise;
            $delta = $direction === 'CREDIT' ? $amount : -$amount;
            $after = $before + $delta;
            if ($after < 0) {
                abort(400, 'Insufficient wallet balance');
            }
            $commission = (int) ($input['commissionPaise'] ?? 0);
            $gross = (int) ($input['grossPaise'] ?? ($amount + $commission));
            $ledger = $this->filterRow('wallet_ledger', [
                'public_ref' => $this->newRef('KCW'),
                'wallet_id' => (int) $wallet->id,
                'booking_id' => $input['bookingId'] ?? null,
                'owner_user_id' => (int) $input['ownerUserId'],
                'account' => $input['ownerType'],
                'owner_type' => $input['ownerType'],
                'direction' => $direction,
                'amount_paise' => $amount,
                'commission_paise' => $commission,
                'gross_paise' => $gross,
                'balance_before_paise' => $before,
                'balance_after_paise' => $after,
                'kind' => $input['kind'] ?? 'trip',
                'status' => $input['status'] ?? 'posted',
                'note' => $input['note'] ?? null,
                'payment_ref' => $input['paymentRef'] ?? null,
                'created_at' => now(),
            ]);
            try {
                $ledger['id'] = $this->db()->table('wallet_ledger')->insertGetId($ledger);
            } catch (QueryException $exception) {
                if ($this->isUniqueConflict($exception) && ! empty($input['bookingId'])) {
                    $existing = $this->db()->table('wallet_ledger')
                        ->where('booking_id', $input['bookingId'])
                        ->where('wallet_id', $wallet->id)
                        ->where('kind', $input['kind'] ?? 'trip')
                        ->where('direction', $direction)
                        ->first();
                    abort_unless($existing, 409, 'Ledger post conflict');

                    return ['wallet' => $wallet, 'ledger' => (array) $existing, 'created' => false];
                }
                Log::warning('wallet.ledger_insert_failed', ['error' => $exception->getMessage()]);
                abort(422, 'Could not save this wallet entry. Try again.');
            }
            $this->db()->table('wallets')->where('id', $wallet->id)->update($this->filterRow('wallets', [
                'balance_paise' => $after,
                'updated_at' => now(),
            ]));

            return ['wallet' => (object) array_merge((array) $wallet, ['balance_paise' => $after]), 'ledger' => $ledger, 'created' => true];
        });
        if (! empty($posted['created'])) {
            try {
                app(NotificationService::class)->dispatch((int) $input['ownerUserId'], 'wallet_transaction', [
                    'amount' => number_format(((int) $input['amountPaise']) / 100, 2, '.', ''),
                    'direction' => strtolower($direction),
                    'note' => $input['note'] ?? $input['kind'] ?? 'Wallet update',
                ], ['entity' => ['type' => 'ledger', 'id' => (string) ($posted['ledger']['id'] ?? '')]]);
            } catch (\Throwable $e) {
                Log::warning('wallet.notify_failed', ['error' => $e->getMessage()]);
            }
        }

        return $posted;
    }

    public function ensureWallet(int $userId, string $ownerType, bool $lock = false): object
    {
        $this->ensureSchema();
        abort_unless($userId > 0, 422, 'This user cannot receive wallet money.');
        $q = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $userId);
        if ($lock) {
            $q->lockForUpdate();
        }
        $wallet = $q->first();
        if ($wallet) {
            return $wallet;
        }
        try {
            $this->db()->table('wallets')->insert($this->filterRow('wallets', [
                'owner_type' => $ownerType,
                'owner_user_id' => $userId,
                'balance_paise' => 0,
                'joining_credit_limit_paise' => 0,
                'joining_credit_paise' => 0,
                'joining_credit_used_paise' => 0,
                'frozen_paise' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        } catch (QueryException $exception) {
            if (! $this->isUniqueConflict($exception)) {
                Log::warning('wallet.create_failed', ['userId' => $userId, 'error' => $exception->getMessage()]);
                abort(422, 'Could not create a wallet for this user.');
            }
        }

        $created = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $userId);
        if ($lock) {
            $created->lockForUpdate();
        }
        $wallet = $created->first();
        abort_unless($wallet, 422, 'Could not create a wallet for this user.');

        return $wallet;
    }

    public function activeRule(): ?object
    {
        return $this->db()->table('commission_rules')->where('active', 1)->where('name', 'default')->first()
            ?? $this->db()->table('commission_rules')->where('active', 1)->orderBy('id')->first();
    }

    /**
     * Seed the documented default rule (10% of base fare) only when no row exists.
     */
    public function ensureDefaultRule(): object
    {
        $rule = $this->activeRule();
        if ($rule) {
            return $rule;
        }
        $payload = [
            'name' => 'default',
            'percent' => 10,
            'active' => 1,
            'on_base_fare' => 1,
            'on_gst' => 0,
            'on_toll' => 0,
            'on_parking' => 0,
            'on_waiting' => 0,
            'on_discount' => 0,
            'on_other' => 0,
        ];
        if ($this->hasColumn('commission_rules', 'on_complete')) {
            $payload['on_complete'] = 0;
        }
        $this->db()->table('commission_rules')->insert($payload);

        return $this->activeRule();
    }

    private function walletAccountForRole(string $role): string
    {
        $role = strtoupper($role);
        if (in_array($role, self::ACCOUNTS, true)) {
            return $role;
        }
        if (in_array($role, [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN], true)) {
            return 'PLATFORM';
        }

        return 'CUSTOMER';
    }

    /**
     * @return array<string, mixed>
     */
    public function shareSummary(?User $operator): array
    {
        $summary = [
            'platformPercent' => FareCommissionSplit::PLATFORM_PERCENT,
            'districtPercent' => FareCommissionSplit::DISTRICT_PERCENT,
            'totalPercent' => FareCommissionSplit::TOTAL_PERCENT,
            'audience' => 'platform',
            'title' => 'Platform 6%',
            'note' => 'A completed fare keeps a 10% commission. Super Admin receives 6% of the fare. The District Head receives 4%, and a fleet cut is taken only from that 4%.',
            'todayPaise' => 0,
            'monthPaise' => 0,
            'fleetPercent' => null,
        ];
        if (! $operator) {
            return $summary;
        }
        if ($operator->isDistrictHead()) {
            $summary['audience'] = 'district';
            $summary['title'] = 'District Head 4%';
            $summary['note'] = 'This is your net share after the fleet cut you assigned. Super Admin still receives 6% of the fare.';
            [$summary['todayPaise'], $summary['monthPaise']] = $this->accountEarnings('DISTRICT_HEAD', $this->platformOwnerId($operator), ['district_head_commission', 'commission']);

            return $summary;
        }
        if ($operator->isFleetOwner()) {
            $summary['audience'] = 'fleet';
            $summary['title'] = 'Fleet cut';
            $summary['note'] = 'Your cut is a percent of fare assigned by the District Head, taken from their 4% share.';
            $summary['fleetPercent'] = $this->fleetCutPercent((int) ($operator->fleet_owner_id ?? 0));
            $fleetUserId = $operator->fleet_owner_id
                ? (int) $this->db()->table('fleet_owners')->where('id', $operator->fleet_owner_id)->value('user_id')
                : 0;
            [$summary['todayPaise'], $summary['monthPaise']] = $this->accountEarnings('FLEET_OWNER', $fleetUserId, ['fleet_commission', 'commission']);

            return $summary;
        }
        [$summary['todayPaise'], $summary['monthPaise']] = $this->accountEarnings('PLATFORM', null, ['platform_commission', 'commission']);

        return $summary;
    }

    private function platformOwnerId(User $operator): int
    {
        if ($operator->nest_user_id) {
            return (int) $operator->nest_user_id;
        }
        if ($operator->email) {
            return (int) ($this->db()->table('users')->where('email', $operator->email)->value('id') ?? 0);
        }

        return 0;
    }

    /**
     * @param  list<string>  $kinds
     * @return array{0: int, 1: int}
     */
    private function accountEarnings(string $account, ?int $ownerUserId, array $kinds): array
    {
        if (! Schema::connection('platform')->hasTable('wallet_ledger')) {
            return [0, 0];
        }
        $q = $this->db()->table('wallet_ledger')->where('direction', 'CREDIT');
        if ($this->hasColumn('wallet_ledger', 'account')) {
            $q->where('account', $account);
        }
        if ($ownerUserId) {
            $q->where('owner_user_id', $ownerUserId);
        }
        if ($this->hasColumn('wallet_ledger', 'kind')) {
            $q->whereIn('kind', $kinds);
        }
        $today = (int) (clone $q)->where('created_at', '>=', now()->startOfDay())->sum('amount_paise');
        $month = (int) (clone $q)->where('created_at', '>=', now()->startOfMonth())->sum('amount_paise');

        return [$today, $month];
    }

    private function fleetCutPercent(int $fleetOwnerId): float
    {
        if ($fleetOwnerId < 1) {
            return 0.0;
        }
        if ($this->hasColumn('fleet_owners', 'commission_percent')) {
            $own = $this->db()->table('fleet_owners')->where('id', $fleetOwnerId)->value('commission_percent');
            if ($own !== null && $own !== '') {
                return (float) $own;
            }
        }
        $stored = $this->setting('fleet_cut_percent_'.$fleetOwnerId);
        if (is_numeric($stored)) {
            return (float) $stored;
        }

        return 0.0;
    }

    /**
     * @param  array<string, int|float>  $split
     */
    private function persistCommissionSplit(object $row, array $split): void
    {
        $patch = [];
        foreach ([
            'commission_paise' => 'commissionPaise',
            'platform_commission_paise' => 'platformPaise',
            'district_head_commission_paise' => 'districtHeadPaise',
            'fleet_commission_paise' => 'fleetPaise',
        ] as $column => $key) {
            if ($this->hasColumn('bookings', $column)) {
                $patch[$column] = (int) ($split[$key] ?? 0);
            }
        }
        if ($patch === []) {
            return;
        }
        $this->db()->table('bookings')->where('id', $row->id)->update($patch);
    }

    private function platformWalletUserId(): ?int
    {
        $configured = $this->setting('platform_wallet_user_id');
        if ($configured && is_numeric($configured)) {
            return (int) $configured;
        }
        $admin = $this->db()->table('users')->whereIn('role', [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN])->orderBy('id')->value('id');

        return $admin ? (int) $admin : null;
    }

    /**
     * @return array<string, int>
     */
    private function bucketsForTotal(object $rule, int $totalPaise): array
    {
        $flags = CommissionEngine::flags($rule);
        $buckets = CommissionEngine::emptyBuckets();
        if ($flags['onComplete'] || $flags['onBaseFare']) {
            $buckets['basePaise'] = $totalPaise;
        }

        return $buckets;
    }

    /**
     * @return array<string, mixed>
     */
    private function lastSettlementForBooking(int $bookingId): array
    {
        $driver = $this->db()->table('wallet_ledger')
            ->where('booking_id', $bookingId)
            ->where('account', 'DRIVER')
            ->where('kind', 'trip')
            ->where('direction', 'CREDIT')
            ->first();

        $booking = $this->db()->table('bookings')->where('id', $bookingId)->first();
        $read = function (string $column) use ($booking): int {
            if (! $booking || ! $this->hasColumn('bookings', $column)) {
                return 0;
            }

            return (int) ($booking->{$column} ?? 0);
        };

        return [
            'commissionPaise' => (int) ($driver->commission_paise ?? 0),
            'netPaise' => (int) ($driver->amount_paise ?? 0),
            'eligibleHintPaise' => (int) ($driver->gross_paise ?? 0),
            'platformCommissionPaise' => $read('platform_commission_paise'),
            'districtHeadCommissionPaise' => $read('district_head_commission_paise'),
            'fleetCommissionPaise' => $read('fleet_commission_paise'),
        ];
    }

    private function scopedWallets(?User $operator): Builder
    {
        $q = $this->db()->table('wallets');
        if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
            $q->where(function ($outer) use ($operator) {
                $outer->whereExists(function ($sub) use ($operator) {
                    $sub->selectRaw('1')->from('users')->whereColumn('users.id', 'wallets.owner_user_id');
                    TerritoryScope::applyUsers($sub, $operator, 'users');
                });
                if ($operator->role === OperatorRole::FLEET_OWNER && $operator->fleet_owner_id) {
                    $outer->orWhere(function ($own) use ($operator) {
                        $own->where('wallets.owner_type', 'FLEET_OWNER')->whereExists(function ($sub) use ($operator) {
                            $sub->selectRaw('1')->from('fleet_owners')
                                ->whereColumn('fleet_owners.user_id', 'wallets.owner_user_id')
                                ->where('fleet_owners.id', $operator->fleet_owner_id);
                        });
                    });
                }
            });
        }

        return $q;
    }

    private function ledgerQuery(?User $operator): Builder
    {
        $q = $this->db()->table('wallet_ledger')
            ->join('wallets', 'wallets.id', '=', 'wallet_ledger.wallet_id')
            ->leftJoin('users', 'users.id', '=', 'wallets.owner_user_id')
            ->select(
                'wallet_ledger.*',
                'wallets.owner_type',
                'wallets.owner_user_id as wallet_owner_user_id',
                'users.name as owner_name',
            );
        if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
            $ids = $this->scopedWallets($operator)->pluck('id');
            if ($ids->isEmpty()) {
                $q->whereRaw('0 = 1');
            } else {
                $q->whereIn('wallet_ledger.wallet_id', $ids);
            }
        }

        return $q;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentWallet(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'walletId' => (string) $row->id,
            'ownerType' => $row->owner_type,
            'ownerUserId' => $row->owner_user_id !== null ? (string) $row->owner_user_id : null,
            'userId' => $row->owner_user_id !== null ? (string) $row->owner_user_id : null,
            'ownerName' => $row->owner_name ?? null,
            'ownerEmail' => $row->owner_email ?? null,
            'balancePaise' => (int) $row->balance_paise,
            'balanceRupees' => ((int) $row->balance_paise) / 100,
            'joiningCreditPaise' => (int) ($row->joining_credit_paise ?? 0),
            'joiningCreditRupees' => ((int) ($row->joining_credit_paise ?? 0)) / 100,
            'frozenPaise' => (int) ($row->frozen_paise ?? 0),
            'frozenRupees' => ((int) ($row->frozen_paise ?? 0)) / 100,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    public function withdrawals(?User $operator, array $filters = []): array
    {
        abort_unless($this->canViewWithdrawals($operator), 403);
        $this->ensureWithdrawalSchema();
        if (! Schema::connection('platform')->hasTable('wallet_withdrawals')) {
            return [];
        }

        $q = $this->db()->table('wallet_withdrawals')
            ->leftJoin('wallets', 'wallets.id', '=', 'wallet_withdrawals.wallet_id');
        if ($this->hasColumn('wallet_withdrawals', 'user_id')) {
            $q->leftJoin('users', 'users.id', '=', 'wallet_withdrawals.user_id');
        } else {
            $q->leftJoin('users', 'users.id', '=', 'wallets.owner_user_id');
        }
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($status !== '' && $status !== 'all') {
            $q->where('wallet_withdrawals.status', $status);
        }
        if ($operator && ! TerritoryScope::isUnrestricted($operator)) {
            $ids = $this->scopedWallets($operator)->pluck('id');
            if ($ids->isEmpty()) {
                $q->whereRaw('0 = 1');
            } else {
                $q->whereIn('wallet_withdrawals.wallet_id', $ids);
            }
        }

        return $q->select(
            'wallet_withdrawals.*',
            'wallets.owner_user_id as wallet_owner_user_id',
            'users.name as owner_name',
            'users.phone as owner_phone',
        )
            ->orderByDesc('wallet_withdrawals.id')
            ->limit(120)
            ->get()
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'publicRef' => $row->public_ref ?? null,
                'userId' => (int) ($row->user_id ?? $row->wallet_owner_user_id ?? 0),
                'walletId' => (int) ($row->wallet_id ?? 0),
                'ownerName' => $row->owner_name ?: 'User #'.($row->user_id ?? $row->wallet_owner_user_id ?? '?'),
                'ownerPhone' => $row->owner_phone,
                'amountRupees' => ((int) $row->amount_paise) / 100,
                'status' => $row->status,
                'transferRef' => $row->transfer_ref ?? null,
                'note' => $row->note,
                'createdAt' => $row->created_at,
            ])->all();
    }

    public function canViewWithdrawals(?User $operator): bool
    {
        return (bool) ($operator?->can('payout.view') || $operator?->can('payout.approve') || $operator?->can('platform.admin'));
    }

    public function canSettleWithdrawals(?User $operator): bool
    {
        return (bool) ($operator?->can('payout.approve') || $operator?->can('platform.admin'));
    }

    public function decideWithdrawal(User $operator, int $id, string $decision, ?string $transferRef = null, ?string $note = null): void
    {
        abort_unless($this->canSettleWithdrawals($operator), 403);
        $this->ensureWithdrawalSchema();
        $row = $this->db()->table('wallet_withdrawals')->where('id', $id)->first();
        abort_unless($row, 404, 'Withdrawal not found.');
        $decision = strtolower($decision);
        abort_unless(in_array($decision, ['approve', 'reject', 'settle'], true), 422, 'Unknown withdrawal action.');
        abort_unless($row->status !== 'settled' && $row->status !== 'rejected', 422, 'This withdrawal is already closed.');
        $wallet = $this->db()->table('wallets')->where('id', $row->wallet_id)->first();
        abort_unless($wallet, 404, 'Wallet not found.');
        $frozen = max(0, (int) ($wallet->frozen_paise ?? 0));
        $amount = (int) $row->amount_paise;
        $reviewer = $operator->nest_user_id ?: null;

        if ($decision === 'approve') {
            $decision = 'settle';
            $note = $note ?: 'Admin approved and paid withdrawal';
            $transferRef = $transferRef ?: ('PAY-'.$id);
        }

        if ($decision === 'reject') {
            if ($this->hasColumn('wallets', 'frozen_paise')) {
                $this->db()->table('wallets')->where('id', $wallet->id)->update([
                    'frozen_paise' => max(0, $frozen - $amount),
                    'updated_at' => now(),
                ]);
            }
            $this->db()->table('wallet_withdrawals')->where('id', $id)->update($this->withdrawalUpdate([
                'status' => 'rejected',
                'note' => $note ?: 'Rejected by admin',
                'reviewed_by' => $reviewer,
                'reviewed_by_id' => $reviewer,
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]));

            return;
        }

        abort_unless(in_array((string) $row->status, ['requested', 'approved'], true), 422, 'Approve this request before settling.');
        $before = (int) $wallet->balance_paise;
        abort_unless($before >= $amount, 422, 'Wallet cash is lower than the frozen withdrawal.');
        $this->postLedger([
            'ownerType' => (string) ($wallet->owner_type ?: 'DRIVER'),
            'ownerUserId' => (int) $wallet->owner_user_id,
            'direction' => 'DEBIT',
            'amountPaise' => $amount,
            'kind' => 'withdraw',
            'note' => $note ?: 'Withdrawal paid successfully',
            'paymentRef' => $transferRef,
        ]);
        $walletUpdate = ['updated_at' => now()];
        if ($this->hasColumn('wallets', 'frozen_paise')) {
            $walletUpdate['frozen_paise'] = max(0, $frozen - $amount);
        }
        $this->db()->table('wallets')->where('id', $wallet->id)->update($walletUpdate);
        $this->db()->table('wallet_withdrawals')->where('id', $id)->update($this->withdrawalUpdate([
            'status' => 'settled',
            'transfer_ref' => $transferRef,
            'note' => $note ?: $row->note,
            'settled_at' => now(),
            'reviewed_by' => $reviewer,
            'reviewed_by_id' => $reviewer,
            'reviewed_at' => now(),
            'updated_at' => now(),
        ]));
    }

    public function ensureWithdrawalSchema(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('wallets') && ! $schema->hasColumn('wallets', 'frozen_paise')) {
            $schema->table('wallets', function ($table) {
                $table->unsignedBigInteger('frozen_paise')->default(0);
            });
        }
        if (! $schema->hasTable('wallet_withdrawals')) {
            $schema->create('wallet_withdrawals', function ($table) {
                $table->id();
                $table->string('public_ref', 24)->unique();
                $table->unsignedBigInteger('wallet_id');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('amount_paise');
                $table->string('status', 24)->default('requested');
                $table->string('payout_method', 16)->nullable();
                $table->string('upi_or_account', 120)->nullable();
                $table->string('transfer_ref', 64)->nullable();
                $table->string('note', 255)->nullable();
                $table->unsignedBigInteger('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamps();
            });

            return;
        }
        $adds = [];
        foreach (['user_id', 'upi_or_account', 'transfer_ref', 'reviewed_by', 'reviewed_at', 'settled_at'] as $column) {
            if (! $schema->hasColumn('wallet_withdrawals', $column)) {
                $adds[] = $column;
            }
        }
        if ($adds !== []) {
            $schema->table('wallet_withdrawals', function ($table) use ($adds) {
                if (in_array('user_id', $adds, true)) {
                    $table->unsignedBigInteger('user_id')->nullable();
                }
                if (in_array('upi_or_account', $adds, true)) {
                    $table->string('upi_or_account', 120)->nullable();
                }
                if (in_array('transfer_ref', $adds, true)) {
                    $table->string('transfer_ref', 64)->nullable();
                }
                if (in_array('reviewed_by', $adds, true)) {
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                }
                if (in_array('reviewed_at', $adds, true)) {
                    $table->timestamp('reviewed_at')->nullable();
                }
                if (in_array('settled_at', $adds, true)) {
                    $table->timestamp('settled_at')->nullable();
                }
            });
        }
        if ($this->hasColumn('wallet_withdrawals', 'user_id') && $this->hasColumn('wallets', 'owner_user_id')) {
            $this->db()->statement(
                'UPDATE wallet_withdrawals w INNER JOIN wallets wa ON wa.id = w.wallet_id SET w.user_id = wa.owner_user_id WHERE w.user_id IS NULL OR w.user_id = 0'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withdrawalUpdate(array $payload): array
    {
        $out = [];
        foreach ($payload as $column => $value) {
            if ($this->hasColumn('wallet_withdrawals', $column)) {
                $out[$column] = $value;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLedger(object $row): array
    {
        $userId = $row->owner_user_id ?? $row->wallet_owner_user_id ?? null;

        return [
            'id' => (string) $row->id,
            'transactionId' => $row->public_ref ?? (string) $row->id,
            'walletId' => $row->wallet_id !== null ? (string) $row->wallet_id : null,
            'bookingId' => isset($row->booking_id) && $row->booking_id ? (string) $row->booking_id : null,
            'userId' => $userId !== null ? (string) $userId : null,
            'account' => $row->account ?? $row->owner_type ?? null,
            'transactionType' => $row->kind ?? 'trip',
            'kind' => $row->kind ?? 'trip',
            'direction' => $row->direction,
            'credit' => ($row->direction ?? '') === 'CREDIT',
            'debit' => ($row->direction ?? '') === 'DEBIT',
            'amountPaise' => (int) $row->amount_paise,
            'amountRupees' => ((int) $row->amount_paise) / 100,
            'commissionPaise' => (int) ($row->commission_paise ?? 0),
            'commissionRupees' => ((int) ($row->commission_paise ?? 0)) / 100,
            'previousBalancePaise' => (int) ($row->balance_before_paise ?? 0),
            'newBalancePaise' => (int) ($row->balance_after_paise ?? 0),
            'paymentReference' => $row->payment_ref ?? null,
            'status' => $row->status ?? 'posted',
            'note' => $row->note ?? null,
            'createdAt' => $row->created_at ?? null,
        ];
    }

    private function setting(string $key): ?string
    {
        $value = $this->db()->table('system_settings')->where('key', $key)->value('value');

        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }

    private function newRef(string $prefix): string
    {
        return substr($prefix.strtoupper(bin2hex(random_bytes(6))), 0, 24);
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            || str_contains($exception->getMessage(), 'UNIQUE')
            || str_contains($exception->getMessage(), 'unique');
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::connection('platform')->hasColumn($table, $column);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filterRow(string $table, array $row): array
    {
        if (! Schema::connection('platform')->hasTable($table)) {
            return $row;
        }

        return array_filter($row, fn ($key) => $this->hasColumn($table, $key), ARRAY_FILTER_USE_KEY);
    }

    private function ensureSchema(): void
    {
        // Schema is owned by migrations. ALTER TABLE in a wallet request locks the table
        // and makes Add money hang for minutes.
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
