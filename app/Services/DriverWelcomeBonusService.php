<?php

namespace App\Services;

use App\Models\Platform\PlatformDriver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DriverWelcomeBonusService
{
    public function creditIfApproved(PlatformDriver $driver): void
    {
        $kyc = strtolower((string) ($driver->kyc_status ?? ''));
        if (! in_array($kyc, ['approved', 'verified', 'active'], true)) {
            return;
        }
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        if ($schema->hasColumn('drivers', 'welcome_bonus_at') && ! empty($driver->welcome_bonus_at)) {
            return;
        }
        $userId = (int) $driver->user_id;
        $user = $schema->hasTable('users') ? $db->table('users')->where('id', $userId)->first() : null;
        $offer = app(TerritoryBonusService::class)->resolve(
            'DRIVER',
            'joining',
            isset($user->state_id) ? (int) $user->state_id : null,
            isset($user->district_id) ? (int) $user->district_id : null,
        );
        if (empty($offer['enabled'])) {
            return;
        }
        $rupees = max(0, min(100000, (int) $offer['amountRupees']));
        if ($rupees < 1) {
            return;
        }
        $paise = $rupees * 100;
        if ($schema->hasTable('incentives') && $db->table('incentives')->where('user_id', $userId)->where('kind', 'driver_joining')->where('status', 'paid')->exists()) {
            $this->markDriver($driver, $paise);

            return;
        }
        $wallet = $schema->hasTable('wallets')
            ? $db->table('wallets')->where('owner_user_id', $userId)->where(function ($q) {
                $q->where('owner_type', 'DRIVER')->orWhereNull('owner_type');
            })->orderByDesc('id')->first()
            : null;
        if (! $wallet && $schema->hasTable('wallets')) {
            $id = $db->table('wallets')->insertGetId(array_filter([
                'owner_user_id' => $userId,
                'owner_type' => 'DRIVER',
                'balance_paise' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($key) => $schema->hasColumn('wallets', $key), ARRAY_FILTER_USE_KEY));
            $wallet = $db->table('wallets')->where('id', $id)->first();
        }
        if (! $wallet) {
            return;
        }
        $before = (int) ($wallet->balance_paise ?? 0);
        $after = $before + $paise;
        $db->table('wallets')->where('id', $wallet->id)->update(array_filter([
            'balance_paise' => $after,
            'updated_at' => now(),
        ], fn ($key) => $schema->hasColumn('wallets', $key), ARRAY_FILTER_USE_KEY));
        if ($schema->hasTable('wallet_ledger')) {
            $db->table('wallet_ledger')->insert(array_filter([
                'public_ref' => 'JB'.strtoupper(Str::random(8)),
                'wallet_id' => $wallet->id,
                'owner_user_id' => $userId,
                'account' => 'DRIVER',
                'direction' => 'CREDIT',
                'amount_paise' => $paise,
                'balance_before_paise' => $before,
                'balance_after_paise' => $after,
                'kind' => 'joining_bonus',
                'status' => 'posted',
                'note' => 'Joining bonus ₹'.$rupees.' credited to wallet',
                'created_at' => now(),
            ], fn ($key) => $schema->hasColumn('wallet_ledger', $key), ARRAY_FILTER_USE_KEY));
        }
        if ($schema->hasTable('incentives')) {
            $db->table('incentives')->insert(array_filter([
                'user_id' => $userId,
                'kind' => 'driver_joining',
                'amount_paise' => $paise,
                'status' => 'paid',
                'note' => 'Driver joining bonus credited to wallet',
                'created_at' => now(),
                'updated_at' => now(),
            ], fn ($key) => $schema->hasColumn('incentives', $key), ARRAY_FILTER_USE_KEY));
        }
        $this->markDriver($driver, $paise);
    }

    private function markDriver(PlatformDriver $driver, int $paise): void
    {
        $patch = [];
        if (Schema::connection('platform')->hasColumn('drivers', 'welcome_bonus_paise')) {
            $patch['welcome_bonus_paise'] = $paise;
        }
        if (Schema::connection('platform')->hasColumn('drivers', 'welcome_bonus_at')) {
            $patch['welcome_bonus_at'] = now();
        }
        if ($patch !== []) {
            $driver->update($patch);
        }
    }
}
