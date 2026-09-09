<?php

namespace App\Services;

use App\Models\User;
use App\Platform\CommissionEngine;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
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
        $q = $this->scopedWallets($operator)
            ->leftJoin('users', 'users.id', '=', 'wallets.owner_user_id')
            ->select('wallets.*', 'users.name as owner_name', 'users.email as owner_email', 'users.role as owner_role');
        $type = $query['owner_type'] ?? $query['ownerType'] ?? null;
        if (is_string($type) && $type !== '') {
            $q->where('wallets.owner_type', $type);
        }

        return $q->orderByDesc('wallets.id')->limit(200)->get()->map(fn ($row) => $this->presentWallet($row))->all();
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

        return $q->orderByDesc('wallet_ledger.id')->limit(200)->get()->map(fn ($row) => $this->presentLedger($row))->all();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
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
        if (! empty($row->vehicle_id)) {
            $vehicle = $this->db()->table('vehicles')->where('id', $row->vehicle_id)->first();
            if ($vehicle?->fleet_owner_id) {
                $fleetUserId = $this->db()->table('fleet_owners')->where('id', $vehicle->fleet_owner_id)->value('user_id');
            }
        }
        if (! $fleetUserId && ! empty($driver->fleet_owner_id)) {
            $fleetUserId = $this->db()->table('fleet_owners')->where('id', $driver->fleet_owner_id)->value('user_id');
        }

        $franchise = null;
        if (! empty($row->district_id)) {
            $franchise = $this->db()->table('franchises')
                ->where('district_id', $row->district_id)
                ->where('status', 'ACTIVE')
                ->first();
        }
        $split = CommissionEngine::splitPlatformCommission((int) $settled['commissionPaise'], [
            'fleetPercent' => (float) ($this->setting('fleet_commission_share_percent') ?? 0),
            'territoryPercent' => $franchise ? (float) $franchise->commission_percent : 0,
        ]);

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
                'kind' => 'commission',
                'note' => 'Fleet share of platform commission',
                'paymentRef' => $paymentRef,
            ]);
        }

        if ($franchise && $split['territoryPaise'] > 0) {
            $this->postLedger([
                'ownerType' => $franchise->kind === 'DISTRICT_HEAD' ? 'DISTRICT_HEAD' : 'FRANCHISE',
                'ownerUserId' => (int) $franchise->owner_user_id,
                'direction' => 'CREDIT',
                'amountPaise' => $split['territoryPaise'],
                'commissionPaise' => $split['territoryPaise'],
                'grossPaise' => (int) $settled['commissionPaise'],
                'bookingId' => (int) $row->id,
                'kind' => 'commission',
                'note' => 'Territory share of platform commission',
                'paymentRef' => $paymentRef,
            ]);
        }

        if (! empty($row->corporate_account_id) && $split['unallocatedPaise'] > 0 && $this->hasColumn('corporate_accounts', 'owner_user_id')) {
            $account = $this->db()->table('corporate_accounts')->where('id', $row->corporate_account_id)->first();
            if ($account?->owner_user_id) {
                $this->postLedger([
                    'ownerType' => 'CORPORATE',
                    'ownerUserId' => (int) $account->owner_user_id,
                    'direction' => 'CREDIT',
                    'amountPaise' => $split['unallocatedPaise'],
                    'commissionPaise' => $split['unallocatedPaise'],
                    'grossPaise' => (int) $settled['commissionPaise'],
                    'bookingId' => (int) $row->id,
                    'kind' => 'commission',
                    'note' => 'Corporate share of unallocated platform commission',
                    'paymentRef' => $paymentRef,
                ]);
            }
        } elseif ($split['unallocatedPaise'] > 0) {
            $platformUserId = $this->platformWalletUserId();
            if ($platformUserId) {
                $this->postLedger([
                    'ownerType' => 'PLATFORM',
                    'ownerUserId' => $platformUserId,
                    'direction' => 'CREDIT',
                    'amountPaise' => $split['unallocatedPaise'],
                    'commissionPaise' => $split['unallocatedPaise'],
                    'grossPaise' => (int) $settled['commissionPaise'],
                    'bookingId' => (int) $row->id,
                    'kind' => 'commission',
                    'note' => 'Platform / admin share of commission',
                    'paymentRef' => $paymentRef,
                ]);
            }
        }
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

        $posted = $this->db()->transaction(function () use ($input, $amount, $direction) {
            $wallet = $this->ensureWallet((int) $input['ownerUserId'], (string) $input['ownerType'], true);
            $before = (int) $wallet->balance_paise;
            $delta = $direction === 'CREDIT' ? $amount : -$amount;
            $after = $before + $delta;
            if ($after < 0) {
                abort(400, 'Insufficient wallet balance');
            }
            $commission = (int) ($input['commissionPaise'] ?? 0);
            $gross = (int) ($input['grossPaise'] ?? ($amount + $commission));
            $ledger = [
                'public_ref' => $this->newRef('KCW'),
                'wallet_id' => (int) $wallet->id,
                'booking_id' => $input['bookingId'] ?? null,
                'account' => $input['ownerType'],
                'direction' => $direction,
                'amount_paise' => $amount,
                'commission_paise' => $commission,
                'gross_paise' => $gross,
                'balance_before_paise' => $before,
                'balance_after_paise' => $after,
                'kind' => $input['kind'] ?? 'trip',
                'status' => $input['status'] ?? 'posted',
                'note' => $input['note'] ?? null,
                'created_at' => now(),
            ];
            if ($this->hasColumn('wallet_ledger', 'owner_user_id')) {
                $ledger['owner_user_id'] = (int) $input['ownerUserId'];
            }
            if ($this->hasColumn('wallet_ledger', 'payment_ref')) {
                $ledger['payment_ref'] = $input['paymentRef'] ?? null;
            }
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
                throw $exception;
            }
            $this->db()->table('wallets')->where('id', $wallet->id)->update(['balance_paise' => $after]);

            return ['wallet' => (object) array_merge((array) $wallet, ['balance_paise' => $after]), 'ledger' => $ledger, 'created' => true];
        });
        if (! empty($posted['created'])) {
            app(NotificationService::class)->dispatch((int) $input['ownerUserId'], 'wallet_transaction', [
                'amount' => number_format(((int) $input['amountPaise']) / 100, 2, '.', ''),
                'direction' => strtolower($direction),
                'note' => $input['note'] ?? $input['kind'] ?? 'Wallet update',
            ], ['entity' => ['type' => 'ledger', 'id' => (string) ($posted['ledger']['id'] ?? '')]]);
        }

        return $posted;
    }

    public function ensureWallet(int $userId, string $ownerType, bool $lock = false): object
    {
        $q = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $userId);
        if ($lock) {
            $q->lockForUpdate();
        }
        $wallet = $q->first();
        if ($wallet) {
            return $wallet;
        }
        $this->db()->table('wallets')->insert([
            'owner_type' => $ownerType,
            'owner_user_id' => $userId,
            'balance_paise' => 0,
        ]);

        $created = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $userId);
        if ($lock) {
            $created->lockForUpdate();
        }

        return $created->first();
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

        return [
            'commissionPaise' => (int) ($driver->commission_paise ?? 0),
            'netPaise' => (int) ($driver->amount_paise ?? 0),
            'eligibleHintPaise' => (int) ($driver->gross_paise ?? 0),
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
        ];
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

    private function db()
    {
        return DB::connection('platform');
    }
}
