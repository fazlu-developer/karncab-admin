<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class OperatorTerritorySync
{
    public static function apply(User $operator): void
    {
        try {
            if (! Schema::connection('platform')->hasTable('users')) {
                return;
            }
            $platform = null;
            if ($operator->nest_user_id) {
                $platform = DB::connection('platform')->table('users')->where('id', $operator->nest_user_id)->first();
            }
            if (! $platform && $operator->email) {
                $platform = DB::connection('platform')->table('users')->where('email', strtolower((string) $operator->email))->first();
            }
            if (! $platform) {
                return;
            }
            $patch = [];
            if (! $operator->nest_user_id) {
                $patch['nest_user_id'] = (int) $platform->id;
            }
            $stateId = $platform->state_id ? (int) $platform->state_id : null;
            $districtId = $platform->district_id ? (int) $platform->district_id : null;
            if ($districtId && ! $stateId && Schema::connection('platform')->hasTable('districts')) {
                $stateId = (int) DB::connection('platform')->table('districts')->where('id', $districtId)->value('state_id');
            }
            $platRole = strtoupper((string) ($platform->role ?? ''));
            $opsRole = strtoupper((string) $operator->role);
            $locationRoles = [
                OperatorRole::STATE_HEAD, OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE,
                OperatorRole::FLEET_OWNER,
            ];
            $sameSeat = in_array($opsRole, $locationRoles, true) && ($platRole === $opsRole || in_array($platRole, $locationRoles, true));
            if ($sameSeat) {
                if ($opsRole === OperatorRole::STATE_HEAD && $stateId && (int) $operator->state_id !== $stateId) {
                    $patch['state_id'] = $stateId;
                }
                if (in_array($opsRole, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true)) {
                    if ($stateId && (int) $operator->state_id !== $stateId) {
                        $patch['state_id'] = $stateId;
                    }
                    if ($districtId && (int) $operator->district_id !== $districtId) {
                        $patch['district_id'] = $districtId;
                    }
                }
                if ($opsRole === OperatorRole::FLEET_OWNER && $stateId && ! $operator->state_id) {
                    $patch['state_id'] = $stateId;
                }
            }
            if ($patch === []) {
                $operator->nest_user_id = $operator->nest_user_id ?: (int) $platform->id;

                return;
            }
            $operator->forceFill($patch);
            if ($operator->exists) {
                $operator->save();
            }
        } catch (Throwable) {
            // Login still works if platform geo cannot be copied.
        }
    }
}
