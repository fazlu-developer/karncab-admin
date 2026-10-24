<?php

namespace App\Services;

use App\Models\User;
use App\Platform\FareCommissionSplit;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommissionSettlementService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(?User $operator = null): array
    {
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        $commissionPaise = 0;
        if ($schema->hasTable('bookings') && $schema->hasColumn('bookings', 'commission_paise')) {
            $commissionPaise = (int) $db->table('bookings')->where('status', 'COMPLETED')->sum('commission_paise');
        }
        $fleetCredited = 0;
        $fleetPaid = 0;
        $platformCredited = 0;
        if ($schema->hasTable('wallet_ledger')) {
            $fleetCredited = (int) $db->table('wallet_ledger')
                ->whereIn('account', ['FLEET_OWNER', 'FLEET'])
                ->where('direction', 'CREDIT')
                ->whereIn('kind', ['fleet_commission', 'fleet_week_settlement', 'commission'])
                ->sum('amount_paise');
            $fleetPaid = (int) $db->table('wallet_ledger')
                ->whereIn('account', ['FLEET_OWNER', 'FLEET'])
                ->where('direction', 'DEBIT')
                ->whereIn('kind', ['settlement_payout', 'payout', 'settlement'])
                ->sum('amount_paise');
            $platformCredited = (int) $db->table('wallet_ledger')
                ->where('account', 'PLATFORM')
                ->where('direction', 'CREDIT')
                ->whereIn('kind', ['platform_commission', 'commission'])
                ->sum('amount_paise');
        }
        $owners = [];
        if ($schema->hasTable('fleet_owners')) {
            $ownerQuery = $db->table('fleet_owners')
                ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
                ->orderBy('fleet_owners.trade_name');
            TerritoryScope::applyFleets($ownerQuery, $operator);
            $rows = $ownerQuery->get([
                    'fleet_owners.id',
                    'fleet_owners.trade_name',
                    'fleet_owners.user_id',
                    'fleet_owners.commission_percent',
                    'users.name as owner_name',
                    'users.phone as owner_phone',
                ]);
            foreach ($rows as $row) {
                $userId = (int) $row->user_id;
                $wallet = null;
                if ($userId > 0 && $schema->hasTable('wallets')) {
                    $q = $db->table('wallets')->where('owner_user_id', $userId);
                    if ($schema->hasColumn('wallets', 'owner_type')) {
                        $q->whereIn('owner_type', ['FLEET_OWNER', 'FLEET']);
                    }
                    $wallet = $q->orderBy('id')->first();
                }
                $credited = 0;
                $paid = 0;
                if ($wallet && $schema->hasTable('wallet_ledger')) {
                    $credited = (int) $db->table('wallet_ledger')
                        ->where('wallet_id', $wallet->id)
                        ->where('direction', 'CREDIT')
                        ->whereIn('kind', ['fleet_commission', 'fleet_week_settlement', 'commission'])
                        ->sum('amount_paise');
                    $paid = (int) $db->table('wallet_ledger')
                        ->where('wallet_id', $wallet->id)
                        ->where('direction', 'DEBIT')
                        ->whereIn('kind', ['settlement_payout', 'payout', 'settlement'])
                        ->sum('amount_paise');
                }
                $balance = (int) ($wallet->balance_paise ?? 0);
                if ($credited < 1 && $balance < 1 && $paid < 1) {
                    continue;
                }
                $owners[] = [
                    'id' => (int) $row->id,
                    'userId' => $userId,
                    'name' => $row->trade_name ?: ($row->owner_name ?: 'Fleet #'.$row->id),
                    'phone' => $row->owner_phone,
                    'percent' => $row->commission_percent !== null && $row->commission_percent !== '' ? (float) $row->commission_percent : 5,
                    'creditedRupees' => $credited / 100,
                    'paidRupees' => $paid / 100,
                    'walletRupees' => $balance / 100,
                ];
            }
        }
        $recent = [];
        if ($schema->hasTable('wallet_ledger')) {
            $recent = $db->table('wallet_ledger')
                ->leftJoin('users', 'users.id', '=', 'wallet_ledger.owner_user_id')
                ->whereIn('wallet_ledger.kind', ['fleet_commission', 'platform_commission', 'settlement_payout'])
                ->orderByDesc('wallet_ledger.id')
                ->limit(40)
                ->get([
                    'wallet_ledger.id',
                    'wallet_ledger.kind',
                    'wallet_ledger.direction',
                    'wallet_ledger.amount_paise',
                    'wallet_ledger.note',
                    'wallet_ledger.status',
                    'wallet_ledger.booking_id',
                    'wallet_ledger.created_at',
                    'users.name as owner_name',
                    'users.phone as owner_phone',
                ]);
        }

        $weeks = $this->weekly($operator);

        return [
            'commissionRupees' => $commissionPaise / 100,
            'fleetShareRupees' => $fleetCredited / 100,
            'fleetPaidRupees' => $fleetPaid / 100,
            'platformKeepRupees' => ($platformCredited > 0 ? $platformCredited : max(0, $commissionPaise - $fleetCredited)) / 100,
            'owners' => $owners,
            'recent' => $recent,
            'weekLabel' => $weeks['label'],
            'fleetWeeks' => $weeks['fleets'],
            'districtWeeks' => $weeks['districts'],
        ];
    }

    /**
     * @return array{label: string, fleets: list<array<string, mixed>>, districts: list<array<string, mixed>>}
     */
    public function weekly(?User $operator = null): array
    {
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        $now = now()->timezone('Asia/Kolkata');
        $start = $now->copy()->startOfWeek(\Carbon\Carbon::MONDAY)->subWeek()->startOfDay();
        $end = $start->copy()->endOfWeek(\Carbon\Carbon::SUNDAY)->endOfDay();
        $label = $start->format('j M').' – '.$end->format('j M Y');
        if (! $schema->hasTable('fleet_owners') || ! $schema->hasTable('bookings')) {
            return ['label' => $label, 'fleets' => [], 'districts' => []];
        }
        $select = ['fleet_owners.id', 'fleet_owners.user_id', 'users.name as owner_name', 'users.phone as owner_phone'];
        foreach (['trade_name', 'district_id', 'state_id', 'commission_percent'] as $column) {
            if ($schema->hasColumn('fleet_owners', $column)) {
                $select[] = 'fleet_owners.'.$column;
            }
        }
        $fleetsQuery = $db->table('fleet_owners')
            ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
            ->orderBy($schema->hasColumn('fleet_owners', 'trade_name') ? 'fleet_owners.trade_name' : 'fleet_owners.id');
        TerritoryScope::applyFleets($fleetsQuery, $operator);
        $fleets = $fleetsQuery->get($select);
        $defaultPercent = 0.0;
        if ($schema->hasTable('system_settings')) {
            $global = $db->table('system_settings')->where('key', 'fleet_owner_commission_percent')->value('value');
            if (is_numeric($global)) {
                $defaultPercent = (float) $global;
            }
        }
        $fleetRows = [];
        $byDistrict = [];
        foreach ($fleets as $fleet) {
            $driverIds = $schema->hasTable('drivers')
                ? $db->table('drivers')->where('fleet_owner_id', $fleet->id)->pluck('id')
                : collect();
            $vehicleIds = $schema->hasTable('vehicles')
                ? $db->table('vehicles')->where('fleet_owner_id', $fleet->id)->pluck('id')
                : collect();
            $bookings = $db->table('bookings')->where('status', 'COMPLETED')
                ->where('updated_at', '>=', $start)
                ->where('updated_at', '<=', $end)
                ->where(function ($q) use ($driverIds, $vehicleIds) {
                    $matched = false;
                    if ($vehicleIds->isNotEmpty()) {
                        $q->orWhereIn('vehicle_id', $vehicleIds);
                        $matched = true;
                    }
                    if ($driverIds->isNotEmpty()) {
                        $q->orWhereIn('driver_id', $driverIds);
                        $matched = true;
                    }
                    if (! $matched) {
                        $q->whereRaw('0 = 1');
                    }
                });
            $count = (clone $bookings)->count();
            $gross = (int) (clone $bookings)->sum($schema->hasColumn('bookings', 'final_fare_paise') ? 'final_fare_paise' : 'quote_paise');
            $percent = isset($fleet->commission_percent) && $fleet->commission_percent !== null && $fleet->commission_percent !== '' ? (float) $fleet->commission_percent : $defaultPercent;
            $percent = min(FareCommissionSplit::DISTRICT_PERCENT, max(0, $percent));
            $fleetPaise = $schema->hasColumn('bookings', 'fleet_commission_paise')
                ? (int) (clone $bookings)->sum('fleet_commission_paise')
                : 0;
            if ($fleetPaise < 1 && $gross > 0) {
                $fleetPaise = (int) round($gross * $percent / 100);
            }
            $districtPaise = $schema->hasColumn('bookings', 'district_head_commission_paise')
                ? (int) (clone $bookings)->sum('district_head_commission_paise')
                : 0;
            if ($districtPaise < 1 && $gross > 0) {
                $pool = (int) round($gross * FareCommissionSplit::DISTRICT_PERCENT / 100);
                $districtPaise = max(0, $pool - $fleetPaise);
            }
            if ($count < 1) {
                continue;
            }
            $head = null;
            if ($fleet->district_id && $schema->hasTable('users')) {
                $head = $db->table('users')->where('role', 'DISTRICT_HEAD')->where('district_id', $fleet->district_id)->orderBy('id')->first();
            }
            $districtName = $fleet->district_id && $schema->hasTable('districts')
                ? $db->table('districts')->where('id', $fleet->district_id)->value('name')
                : null;
            $fleetRows[] = [
                'id' => (int) $fleet->id,
                'name' => $fleet->trade_name ?: ($fleet->owner_name ?: 'Fleet #'.$fleet->id),
                'phone' => $fleet->owner_phone,
                'district' => $districtName,
                'districtHead' => $head->name ?? 'Not assigned',
                'bookings' => $count,
                'grossRupees' => $gross / 100,
                'fleetRupees' => $fleetPaise / 100,
                'districtRupees' => $districtPaise / 100,
            ];
            $key = (int) ($fleet->district_id ?? 0);
            if (! isset($byDistrict[$key])) {
                $byDistrict[$key] = [
                    'district' => $districtName ?: 'Unassigned district',
                    'districtHead' => $head->name ?? 'Not assigned',
                    'userId' => (int) ($head->id ?? 0),
                    'bookings' => 0,
                    'amountRupees' => 0,
                ];
            }
            $byDistrict[$key]['bookings'] += $count;
            $byDistrict[$key]['amountRupees'] += $districtPaise / 100;
        }

        return ['label' => $label, 'fleets' => $fleetRows, 'districts' => array_values($byDistrict)];
    }

    public function payout(User $operator, int $fleetOwnerId, ?string $note = null, ?string $bankReference = null, ?float $amountRupees = null, ?string $weekLabel = null): array
    {
        abort_unless($this->canSettle($operator), 403);
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        $visible = $db->table('fleet_owners')->where('id', $fleetOwnerId);
        TerritoryScope::applyFleets($visible, $operator);
        abort_unless($visible->exists(), 404, 'Fleet owner not found.');
        $fleet = $db->table('fleet_owners')->where('id', $fleetOwnerId)->first();
        abort_unless($fleet, 404, 'Fleet owner not found.');
        $userId = (int) $fleet->user_id;
        abort_unless($userId > 0, 422, 'This fleet has no login user.');
        $q = $db->table('wallets')->where('owner_user_id', $userId);
        if ($schema->hasColumn('wallets', 'owner_type')) {
            $q->whereIn('owner_type', ['FLEET_OWNER', 'FLEET']);
        }
        $wallet = $q->orderBy('id')->first();
        if (! $wallet && $amountRupees !== null) {
            $id = $db->table('wallets')->insertGetId(array_filter([
                'owner_user_id' => $userId,
                'owner_type' => 'FLEET_OWNER',
                'balance_paise' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($key) => $schema->hasColumn('wallets', $key), ARRAY_FILTER_USE_KEY));
            $wallet = $db->table('wallets')->where('id', $id)->first();
        }
        abort_unless($wallet, 422, 'Fleet wallet is empty.');
        $before = (int) $wallet->balance_paise;
        if ($amountRupees !== null && $amountRupees > 0) {
            $amount = (int) round($amountRupees * 100);
        } else {
            $amount = $before;
        }
        abort_unless($amount > 0, 422, 'Nothing left to settle for this fleet owner.');
        $after = max(0, $before - $amount);
        $db->table('wallets')->where('id', $wallet->id)->update([
            'balance_paise' => $after,
            'updated_at' => now(),
        ]);
        $paidNote = 'Weekly fleet commission paid to the owner bank account';
        if ($weekLabel) {
            $paidNote = 'Week '.$weekLabel.' fleet commission paid to the owner bank account';
        }
        $db->table('wallet_ledger')->insert(array_filter([
            'public_ref' => 'ST'.strtoupper(Str::random(10)),
            'wallet_id' => $wallet->id,
            'owner_user_id' => $userId,
            'account' => 'FLEET_OWNER',
            'direction' => 'DEBIT',
            'amount_paise' => $amount,
            'commission_paise' => $amount,
            'gross_paise' => $amount,
            'balance_before_paise' => $before,
            'balance_after_paise' => $after,
            'kind' => 'settlement_payout',
            'status' => 'settled',
            'note' => trim(($bankReference ? 'Bank transfer '.$bankReference.'. ' : '').($note ?: $paidNote)),
            'created_at' => now(),
        ], fn ($key) => $schema->hasColumn('wallet_ledger', $key), ARRAY_FILTER_USE_KEY));

        return ['ok' => true, 'amountRupees' => $amount / 100];
    }

    public function payoutDistrict(User $operator, int $userId, float $rupees, string $bankReference, string $weekLabel): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin') || $operator->isStateHead(), 403);
        abort_unless($userId > 0 && $rupees > 0, 422, 'Nothing to settle for this district head.');
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        $user = $db->table('users')->where('id', $userId)->where('role', 'DISTRICT_HEAD')->first();
        abort_unless($user, 404, 'District head not found.');
        $wallet = null;
        if ($schema->hasTable('wallets')) {
            $q = $db->table('wallets')->where('owner_user_id', $userId);
            if ($schema->hasColumn('wallets', 'owner_type')) {
                $q->where('owner_type', 'DISTRICT_HEAD');
            }
            $wallet = $q->orderBy('id')->first();
            if (! $wallet) {
                $id = $db->table('wallets')->insertGetId(array_filter([
                    'owner_user_id' => $userId,
                    'owner_type' => 'DISTRICT_HEAD',
                    'balance_paise' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], fn ($key) => $schema->hasColumn('wallets', $key), ARRAY_FILTER_USE_KEY));
                $wallet = $db->table('wallets')->where('id', $id)->first();
            }
        }
        abort_unless($wallet, 422, 'District wallet is not available.');
        $amount = (int) round($rupees * 100);
        $before = (int) $wallet->balance_paise;
        $after = max(0, $before - $amount);
        $db->table('wallets')->where('id', $wallet->id)->update([
            'balance_paise' => $after,
            'updated_at' => now(),
        ]);
        $db->table('wallet_ledger')->insert(array_filter([
            'public_ref' => 'DH'.strtoupper(Str::random(10)),
            'wallet_id' => $wallet->id,
            'owner_user_id' => $userId,
            'account' => 'DISTRICT_HEAD',
            'direction' => 'DEBIT',
            'amount_paise' => $amount,
            'balance_before_paise' => $before,
            'balance_after_paise' => $after,
            'kind' => 'settlement_payout',
            'status' => 'settled',
            'note' => 'Bank transfer '.$bankReference.'. Week '.$weekLabel.' district commission paid',
            'created_at' => now(),
        ], fn ($key) => $schema->hasColumn('wallet_ledger', $key), ARRAY_FILTER_USE_KEY));

        return ['ok' => true, 'amountRupees' => $amount / 100];
    }

    public function canSettle(?User $operator): bool
    {
        if (! $operator) {
            return false;
        }

        return $operator->can('payments.edit')
            || $operator->can('platform.admin')
            || $operator->isStateHead()
            || $operator->isDistrictHead();
    }
}
