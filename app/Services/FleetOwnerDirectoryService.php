<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class FleetOwnerDirectoryService
{
    /**
     * @return list<object>
     */
    public function list(User $actor): array
    {
        $q = $this->db()->table('fleet_owners')
            ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
            ->leftJoin('states', 'states.id', '=', 'fleet_owners.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'fleet_owners.district_id')
            ->select(
                'fleet_owners.*',
                'users.name as owner_name',
                'users.email',
                'users.phone',
                'users.district_id as user_district_id',
                'users.state_id as user_state_id',
                'states.name as state_name',
                'districts.name as district_name',
            );
        TerritoryScope::applyFleets($q, $actor);

        return $q->orderByDesc('fleet_owners.id')->limit(300)->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formMeta(User $actor): array
    {
        $states = $this->db()->table('states')->orderBy('name')->get();
        $districts = $this->db()->table('districts')->orderBy('name')->get();
        $franchises = $this->db()->table('franchises')->where('status', 'ACTIVE')->orderBy('trade_name')->get();
        if ($actor->isStateHead() && $actor->state_id) {
            $districts = $districts->where('state_id', $actor->state_id)->values();
            $franchises = $franchises->where('state_id', $actor->state_id)->values();
        }
        if (in_array($actor->role, [OperatorRole::FRANCHISE, OperatorRole::DISTRICT_HEAD], true) && $actor->district_id) {
            $districts = $districts->where('id', $actor->district_id)->values();
            $franchises = $franchises->where('district_id', $actor->district_id)->values();
        }

        return compact('states', 'districts', 'franchises');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): int
    {
        $stateId = (int) $data['state_id'];
        $districtId = (int) $data['district_id'];
        $this->assertGeo($actor, $stateId, $districtId);
        $district = $this->db()->table('districts')->where('id', $districtId)->first();
        abort_if($district === null, 422, 'Unknown district.');
        abort_unless((int) $district->state_id === $stateId, 422, 'District does not belong to the selected state.');

        $userId = $this->db()->table('users')->insertGetId([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password_hash' => Hash::make($data['password']),
            'role' => OperatorRole::FLEET_OWNER,
            'status' => $data['status'] ?? 'ACTIVE',
            'state_id' => $stateId,
            'district_id' => $districtId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'user_id' => $userId,
            'trade_name' => $data['trade_name'],
        ];
        if (Schema::connection('platform')->hasColumn('fleet_owners', 'state_id')) {
            $payload['state_id'] = $stateId;
            $payload['district_id'] = $districtId;
            $payload['franchise_id'] = $data['franchise_id'] ?? null;
            $payload['status'] = $data['status'] ?? 'ACTIVE';
            $payload['address'] = $data['address'] ?? null;
        }

        $fleetId = $this->db()->table('fleet_owners')->insertGetId($payload);
        OrganizationAudit::record($actor, 'fleet_owner.created', 'fleet_owner', $fleetId, null, $payload);

        return $fleetId;
    }

    private function assertGeo(User $actor, int $stateId, int $districtId): void
    {
        if ($actor->isPrivilegedOperator() || ($actor->isManager() && ! $actor->state_id && ! $actor->district_id)) {
            return;
        }
        if ($actor->isStateHead() || ($actor->isManager() && $actor->state_id)) {
            abort_unless((int) $actor->state_id === $stateId, 403, 'You can only create fleet owners in your state.');

            return;
        }
        if (in_array($actor->role, [OperatorRole::FRANCHISE, OperatorRole::DISTRICT_HEAD], true)) {
            abort_unless((int) $actor->district_id === $districtId, 403, 'You can only create fleet owners in your district.');
        }
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
