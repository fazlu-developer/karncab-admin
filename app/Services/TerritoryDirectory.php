<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TerritoryDirectory
{
    /**
     * @return array{states: list<object>, districts: list<object>}
     */
    public function heads(?User $actor = null): array
    {
        $states = DB::connection('platform')->table('states')
            ->orderBy('name')
            ->get($this->existingColumns('states', ['id', 'name', 'code', 'status']));

        $districtSelect = [
            'districts.id',
            'districts.name',
            'districts.state_id',
            'states.name as state_name',
        ];
        if (Schema::connection('platform')->hasColumn('districts', 'status')) {
            $districtSelect[] = 'districts.status';
        }
        if (Schema::connection('platform')->hasColumn('districts', 'code')) {
            $districtSelect[] = 'districts.code';
        }

        $districts = DB::connection('platform')->table('districts')
            ->join('states', 'states.id', '=', 'districts.state_id')
            ->orderBy('states.name')
            ->orderBy('districts.name')
            ->get($districtSelect);

        if ($actor && ! TerritoryScope::isUnrestricted($actor)) {
            $role = TerritoryScope::effectiveRole($actor);
            if ($role === OperatorRole::STATE_HEAD && $actor->state_id) {
                $states = $states->where('id', (int) $actor->state_id)->values();
                $districts = $districts->where('state_id', (int) $actor->state_id)->values();
            } elseif (in_array($role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true) && $actor->district_id) {
                $districts = $districts->where('id', (int) $actor->district_id)->values();
                $stateIds = $districts->pluck('state_id')->unique()->all();
                $states = $states->whereIn('id', $stateIds)->values();
            }
        }

        $stateHeads = DB::connection('platform')->table('users')
            ->where('role', OperatorRole::STATE_HEAD)
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'email', 'state_id']);
        $districtHeads = DB::connection('platform')->table('users')
            ->whereIn('role', [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE])
            ->where('status', 'ACTIVE')
            ->orderBy('id')
            ->get(['id', 'name', 'phone', 'email', 'district_id', 'role']);

        $byState = [];
        foreach ($stateHeads as $head) {
            $key = (int) $head->state_id;
            if ($key > 0 && ! isset($byState[$key])) {
                $byState[$key] = $head;
            }
        }
        $byDistrict = [];
        foreach ($districtHeads as $head) {
            $key = (int) $head->district_id;
            if ($key > 0 && ! isset($byDistrict[$key])) {
                $byDistrict[$key] = $head;
            }
        }

        $stateRows = [];
        foreach ($states as $state) {
            $head = $byState[(int) $state->id] ?? null;
            $stateRows[] = (object) [
                'id' => $state->id,
                'name' => $state->name,
                'code' => $state->code ?? null,
                'status' => $state->status ?? 'ACTIVE',
                'head_name' => $head->name ?? null,
                'head_phone' => $head->phone ?? null,
                'head_email' => $head->email ?? null,
            ];
        }

        $districtRows = [];
        foreach ($districts as $district) {
            $head = $byDistrict[(int) $district->id] ?? null;
            $districtRows[] = (object) [
                'id' => $district->id,
                'name' => $district->name,
                'state_name' => $district->state_name,
                'status' => $district->status ?? 'ACTIVE',
                'head_name' => $head->name ?? null,
                'head_phone' => $head->phone ?? null,
                'head_email' => $head->email ?? null,
            ];
        }

        return [
            'states' => $stateRows,
            'districts' => $districtRows,
        ];
    }

    /**
     * Live `states` has no status column; tests and newer snapshots may.
     *
     * @param  list<string>  $wanted
     * @return list<string>
     */
    private function existingColumns(string $table, array $wanted): array
    {
        $schema = Schema::connection('platform');

        return array_values(array_filter(
            $wanted,
            fn (string $column) => $schema->hasColumn($table, $column),
        ));
    }
}
