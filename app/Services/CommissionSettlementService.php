<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommissionSettlementService
{
    /**
     * @return array<string, mixed>
     */
    public function overview(): array
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
                ->whereIn('kind', ['fleet_commission', 'commission'])
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
            $rows = $db->table('fleet_owners')
                ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
                ->orderBy('fleet_owners.trade_name')
                ->get([
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
                        ->whereIn('kind', ['fleet_commission', 'commission'])
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

        return [
            'commissionRupees' => $commissionPaise / 100,
            'fleetShareRupees' => $fleetCredited / 100,
            'fleetPaidRupees' => $fleetPaid / 100,
            'platformKeepRupees' => ($platformCredited > 0 ? $platformCredited : max(0, $commissionPaise - $fleetCredited)) / 100,
            'owners' => $owners,
            'recent' => $recent,
        ];
    }

    public function payout(User $operator, int $fleetOwnerId, ?string $note = null): array
    {
        abort_unless($operator->can('payments.edit') || $operator->can('platform.admin'), 403);
        $db = DB::connection('platform');
        $fleet = $db->table('fleet_owners')->where('id', $fleetOwnerId)->first();
        abort_unless($fleet, 404, 'Fleet owner not found.');
        $userId = (int) $fleet->user_id;
        abort_unless($userId > 0, 422, 'This fleet has no login user.');
        $q = $db->table('wallets')->where('owner_user_id', $userId);
        if (Schema::connection('platform')->hasColumn('wallets', 'owner_type')) {
            $q->whereIn('owner_type', ['FLEET_OWNER', 'FLEET']);
        }
        $wallet = $q->orderBy('id')->first();
        abort_unless($wallet, 422, 'Fleet wallet is empty.');
        $amount = (int) $wallet->balance_paise;
        abort_unless($amount > 0, 422, 'Nothing left to settle for this fleet owner.');
        $before = $amount;
        $after = 0;
        $db->table('wallets')->where('id', $wallet->id)->update([
            'balance_paise' => 0,
            'updated_at' => now(),
        ]);
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
            'note' => $note ?: 'Admin settled fleet 5% commission payout',
            'created_at' => now(),
        ], fn ($key) => Schema::connection('platform')->hasColumn('wallet_ledger', $key), ARRAY_FILTER_USE_KEY));

        return ['ok' => true, 'amountRupees' => $amount / 100];
    }
}
