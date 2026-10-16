<?php

namespace App\Services;

use App\Models\User;
use App\Platform\FareCommissionSplit;
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

        return [
            'district' => $ctx,
            'capPercent' => FareCommissionSplit::DISTRICT_PERCENT,
            'fleets' => $this->fleetRows($ctx['districtId']),
        ];
    }

    public function saveFleetCut(User $operator, int $fleetId, float $percent, array $query = []): void
    {
        abort_unless($operator->can('fleet.manage') || $operator->can('commission.edit'), 403);
        abort_unless($percent >= 0 && $percent <= FareCommissionSplit::DISTRICT_PERCENT, 422, 'Fleet cut cannot exceed the District Head 4% share.');
        $ctx = $this->context($operator, $query);
        $fleet = $this->fleetQuery($ctx['districtId'])->where('fleet_owners.id', $fleetId)->first();
        abort_if($fleet === null, 404, 'Fleet is not in this district.');
        $schema = Schema::connection('platform');
        if ($schema->hasColumn('fleet_owners', 'commission_percent')) {
            $patch = ['commission_percent' => $percent];
            if ($schema->hasColumn('fleet_owners', 'updated_at')) {
                $patch['updated_at'] = now();
            }
            $this->db()->table('fleet_owners')->where('id', $fleetId)->update($patch);
        }
        if ($schema->hasTable('system_settings')) {
            $key = 'fleet_cut_percent_'.$fleetId;
            $existing = $this->db()->table('system_settings')->where('key', $key)->first();
            $row = ['value' => (string) $percent];
            if ($schema->hasColumn('system_settings', 'updated_at')) {
                $row['updated_at'] = now();
            }
            if ($existing) {
                $this->db()->table('system_settings')->where('key', $key)->update($row);
            } else {
                $row['key'] = $key;
                if ($schema->hasColumn('system_settings', 'created_at')) {
                    $row['created_at'] = now();
                }
                $this->db()->table('system_settings')->insert($row);
            }
        }
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
            ->join('users', 'users.id', '=', 'fleet_owners.user_id')
            ->where('users.district_id', $districtId);
    }

    /**
     * @return list<array{id: int, name: string, percent: float}>
     */
    private function fleetRows(int $districtId): array
    {
        $schema = Schema::connection('platform');
        $select = ['fleet_owners.id', 'fleet_owners.trade_name', 'users.name as owner_name'];
        if ($schema->hasColumn('fleet_owners', 'commission_percent')) {
            $select[] = 'fleet_owners.commission_percent';
        }
        $rows = $this->fleetQuery($districtId)->orderBy('fleet_owners.id')->get($select);

        return $rows->map(function ($row) use ($schema) {
            $percent = 0.0;
            if ($schema->hasColumn('fleet_owners', 'commission_percent') && $row->commission_percent !== null && $row->commission_percent !== '') {
                $percent = (float) $row->commission_percent;
            } elseif ($schema->hasTable('system_settings')) {
                $stored = $this->db()->table('system_settings')->where('key', 'fleet_cut_percent_'.$row->id)->value('value');
                if (is_numeric($stored)) {
                    $percent = (float) $stored;
                }
            }

            return [
                'id' => (int) $row->id,
                'name' => (string) ($row->trade_name ?: $row->owner_name),
                'percent' => min(FareCommissionSplit::DISTRICT_PERCENT, max(0, $percent)),
            ];
        })->all();
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
