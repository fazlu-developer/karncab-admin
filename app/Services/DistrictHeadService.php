<?php

namespace App\Services;

use App\Models\User;
use App\Platform\FareCommissionSplit;
use App\Platform\FleetDhShare;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DistrictHeadService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array{districtId: int, districtName: string, stateId: int, stateName: string}
     */
    public function context(User $operator, array $query = []): array
    {
        if ($operator->isDistrictHead()) {
            abort_if(! $operator->district_id, 409, 'No district is assigned to this District Head.');
            $districtId = (int) $operator->district_id;
        } elseif (TerritoryScope::isUnrestricted($operator)) {
            $districtId = (int) ($query['districtId'] ?? $operator->district_id ?? 0);
            if ($districtId < 1 && Schema::connection('platform')->hasTable('districts')) {
                $districtId = (int) ($this->db()->table('districts')->orderBy('name')->value('id') ?? 0);
            }
            abort_if($districtId < 1, 422, 'Choose a district.');
        } else {
            abort(403);
        }

        $district = $this->db()->table('districts')->where('id', $districtId)->first();
        abort_if($district === null, 404, 'District not found.');
        $stateName = (string) ($this->db()->table('states')->where('id', $district->state_id)->value('name') ?? '');

        return [
            'districtId' => (int) $district->id,
            'districtName' => (string) $district->name,
            'stateId' => (int) $district->state_id,
            'stateName' => $stateName,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function dashboard(User $operator, array $query = []): array
    {
        abort_unless($operator->can('dashboard.view'), 403);
        $ctx = $this->context($operator, $query);
        $bookings = $this->db()->table('bookings')->where('district_id', $ctx['districtId']);
        $completed = (clone $bookings)->where('status', 'COMPLETED');
        $schema = Schema::connection('platform');
        $fareCol = $schema->hasColumn('bookings', 'final_fare_paise') ? 'final_fare_paise' : 'quote_paise';
        $today = (clone $completed)->where('created_at', '>=', now()->startOfDay());
        $month = (clone $completed)->where('created_at', '>=', now()->startOfMonth());
        $todayFare = (int) (clone $today)->sum($fareCol);
        $monthFare = (int) (clone $month)->sum($fareCol);

        return [
            'district' => $ctx,
            'kpis' => [
                'bookings' => (clone $bookings)->count(),
                'completed' => (clone $completed)->count(),
                'revenueRupees' => ((int) (clone $completed)->sum($fareCol)) / 100,
                'vehicles' => $this->vehicles($ctx['districtId'])->count(),
                'drivers' => $this->drivers($ctx['districtId'])->count(),
                'fleets' => $this->fleetQuery($ctx['districtId'])->count(),
                'todayCommissionRupees' => $this->districtSharePaise($today, $todayFare) / 100,
                'monthCommissionRupees' => $this->districtSharePaise($month, $monthFare) / 100,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function settings(User $operator, array $query = []): array
    {
        abort_unless($operator->can('fleet.manage') || $operator->can('commission.edit'), 403);
        $ctx = $this->context($operator, $query);

        $districts = [];
        if (TerritoryScope::isUnrestricted($operator) && Schema::connection('platform')->hasTable('districts')) {
            $districts = $this->db()->table('districts')->orderBy('name')->get(['id', 'name'])
                ->map(fn ($row) => ['id' => (int) $row->id, 'name' => (string) $row->name])->all();
        }

        return [
            'district' => $ctx,
            'districts' => $districts,
            'capPercent' => 100,
            'fleets' => $this->fleetRows($ctx['districtId']),
        ];
    }

    public function saveFleetCut(User $operator, int $fleetId, ?float $percent, ?int $paise, array $query = []): void
    {
        abort_unless($operator->can('fleet.manage') || $operator->can('commission.edit') || $operator->can('platform.admin'), 403);
        if ($percent !== null) {
            abort_unless($percent >= 0 && $percent <= 100, 422, 'Fleet share is a percent of the District Head commission, from 0 to 100.');
        }
        $ctx = $this->context($operator, $query);
        $fleet = $this->fleetQuery($ctx['districtId'])->where('fleet_owners.id', $fleetId)->first();
        abort_if($fleet === null, 404, 'Fleet is not in this district.');
        FleetDhShare::ensure();
        $patch = [
            'dh_share_percent' => $percent,
            'dh_share_paise' => $paise !== null && $paise > 0 && $percent === null ? $paise : null,
        ];
        if (Schema::connection('platform')->hasColumn('fleet_owners', 'updated_at')) {
            $patch['updated_at'] = now();
        }
        $this->db()->table('fleet_owners')->where('id', $fleetId)->update($patch);
    }

    private function districtSharePaise($completed, int $farePaise): int
    {
        $schema = Schema::connection('platform');
        if ($schema->hasColumn('bookings', 'district_head_commission_paise')) {
            return (int) (clone $completed)->sum('district_head_commission_paise');
        }

        return (int) round($farePaise * FareCommissionSplit::DISTRICT_PERCENT / 100);
    }

    private function vehicles(int $districtId)
    {
        return $this->db()->table('vehicles')->where('district_id', $districtId);
    }

    private function drivers(int $districtId)
    {
        return $this->db()->table('drivers')->whereExists(function ($sub) use ($districtId) {
            $sub->selectRaw('1')
                ->from('users')
                ->whereColumn('users.id', 'drivers.user_id')
                ->where('users.district_id', $districtId);
        });
    }

    private function fleetQuery(int $districtId)
    {
        return $this->db()->table('fleet_owners')
            ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
            ->where(function ($q) use ($districtId) {
                $q->where('users.district_id', $districtId);
                if (Schema::connection('platform')->hasColumn('fleet_owners', 'district_id')) {
                    $q->orWhere('fleet_owners.district_id', $districtId);
                }
            });
    }

    /**
     * @return list<array{id: int, name: string, percent: float}>
     */
    private function fleetRows(int $districtId): array
    {
        $schema = Schema::connection('platform');
        $select = ['fleet_owners.id', 'fleet_owners.trade_name', 'users.name as owner_name'];
        foreach (['commission_percent', 'dh_share_percent', 'dh_share_paise'] as $column) {
            if ($schema->hasColumn('fleet_owners', $column)) {
                $select[] = 'fleet_owners.'.$column;
            }
        }
        $rows = $this->fleetQuery($districtId)->orderBy('fleet_owners.id')->get($select);

        FleetDhShare::ensure();

        return $rows->map(function ($row) use ($schema) {
            $percent = null;
            $paise = null;
            if ($schema->hasColumn('fleet_owners', 'dh_share_percent') && $row->dh_share_percent !== null && $row->dh_share_percent !== '') {
                $percent = (float) $row->dh_share_percent;
            }
            if ($schema->hasColumn('fleet_owners', 'dh_share_paise') && $row->dh_share_paise !== null && (int) $row->dh_share_paise > 0) {
                $paise = (int) $row->dh_share_paise;
            }

            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->trade_name ?: $row->owner_name),
                'percent' => $percent,
                'paise' => $paise,
            ];
        })->all();
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
