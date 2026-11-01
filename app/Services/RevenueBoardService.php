<?php

namespace App\Services;

use App\Models\User;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RevenueBoardService
{
    /**
     * @return array<string, mixed>
     */
    public function board(User $actor, ?int $stateId = null): array
    {
        $db = DB::connection('platform');
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('bookings')) {
            return $this->emptyBoard();
        }
        $fare = $schema->hasColumn('bookings', 'final_fare_paise') ? 'final_fare_paise' : 'quote_paise';
        $completed = $db->table('bookings as b')->where('b.status', 'COMPLETED');
        TerritoryScope::applyBookings($completed, $actor, 'b');
        if ($stateId && TerritoryScope::isUnrestricted($actor)) {
            TerritoryScope::restrictBookingsToStateId($completed, $stateId, 'b');
        } elseif ($actor->isStateHead() && $actor->state_id) {
            $stateId = (int) $actor->state_id;
        } elseif ($actor->isDistrictHead() && $actor->state_id) {
            $stateId = (int) $actor->state_id;
        }

        $today = (clone $completed)->where('b.created_at', '>=', now()->timezone('Asia/Kolkata')->startOfDay()->utc());
        $month = (clone $completed)->where('b.created_at', '>=', now()->timezone('Asia/Kolkata')->startOfMonth()->utc());

        $rows = (clone $completed)
            ->leftJoin('districts as d', 'd.id', '=', 'b.district_id')
            ->leftJoin('states as s', 's.id', '=', 'd.state_id')
            ->groupBy('s.id', 's.name', 'd.id', 'd.name')
            ->orderBy('s.name')
            ->orderBy('d.name')
            ->get([
                's.id as state_id',
                's.name as state_name',
                'd.id as district_id',
                'd.name as district_name',
                DB::raw('COUNT(*) as trips'),
                DB::raw('COALESCE(SUM(b.'.$fare.'), 0) as fare_paise'),
                DB::raw($schema->hasColumn('bookings', 'commission_paise') ? 'COALESCE(SUM(b.commission_paise), 0) as commission_paise' : '0 as commission_paise'),
            ]);

        $states = [];
        foreach ($rows as $row) {
            $key = (string) ($row->state_id ?: 0);
            if (! isset($states[$key])) {
                $states[$key] = [
                    'id' => (int) ($row->state_id ?: 0),
                    'name' => $row->state_name ?: 'Unassigned',
                    'trips' => 0,
                    'fareRupees' => 0,
                    'commissionRupees' => 0,
                    'districts' => [],
                ];
            }
            $fareRupees = ((int) $row->fare_paise) / 100;
            $commissionRupees = ((int) $row->commission_paise) / 100;
            $states[$key]['trips'] += (int) $row->trips;
            $states[$key]['fareRupees'] += $fareRupees;
            $states[$key]['commissionRupees'] += $commissionRupees;
            $states[$key]['districts'][] = [
                'id' => (int) ($row->district_id ?: 0),
                'name' => $row->district_name ?: 'Unassigned',
                'trips' => (int) $row->trips,
                'fareRupees' => $fareRupees,
                'commissionRupees' => $commissionRupees,
            ];
        }

        $stateOptions = [];
        if (TerritoryScope::isUnrestricted($actor) && $schema->hasTable('states')) {
            $stateOptions = $db->table('states')->orderBy('name')->get(['id', 'name'])->all();
        }

        return [
            'states' => array_values($states),
            'stateOptions' => $stateOptions,
            'selectedStateId' => $stateId,
            'trips' => (int) (clone $completed)->count(),
            'todayRupees' => ((int) $today->sum('b.'.$fare)) / 100,
            'monthRupees' => ((int) $month->sum('b.'.$fare)) / 100,
            'fareRupees' => array_sum(array_column($states, 'fareRupees')),
            'commissionRupees' => array_sum(array_column($states, 'commissionRupees')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBoard(): array
    {
        return [
            'states' => [],
            'stateOptions' => [],
            'selectedStateId' => null,
            'trips' => 0,
            'todayRupees' => 0,
            'monthRupees' => 0,
            'fareRupees' => 0,
            'commissionRupees' => 0,
        ];
    }
}
