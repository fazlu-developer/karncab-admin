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
            if (Schema::connection('platform')->hasColumn('fleet_owners', 'kyc_status')) {
                $payload['kyc_status'] = ($payload['status'] ?? 'ACTIVE') === 'ACTIVE' ? 'approved' : 'pending';
            }
        }

        $fleetId = $this->db()->table('fleet_owners')->insertGetId($payload);
        OrganizationAudit::record($actor, 'fleet_owner.created', 'fleet_owner', $fleetId, null, $payload);

        return $fleetId;
    }

    public function find(User $actor, int $id): object
    {
        $row = $this->db()->table('fleet_owners')
            ->leftJoin('users', 'users.id', '=', 'fleet_owners.user_id')
            ->leftJoin('states', 'states.id', '=', 'fleet_owners.state_id')
            ->leftJoin('districts', 'districts.id', '=', 'fleet_owners.district_id')
            ->select(
                'fleet_owners.*',
                'users.name as owner_name',
                'users.email',
                'users.phone',
                'users.status as user_status',
                'states.name as state_name',
                'districts.name as district_name',
            )
            ->where('fleet_owners.id', $id)
            ->first();
        abort_if($row === null, 404);
        $visible = $this->db()->table('fleet_owners')->where('id', $id);
        TerritoryScope::applyFleets($visible, $actor);
        abort_unless($visible->exists(), 404);

        return $row;
    }

    public function verify(User $actor, int $id, bool $approve, ?string $reason = null): void
    {
        $row = $this->find($actor, $id);
        $patch = [
            'status' => $approve ? 'ACTIVE' : 'PENDING',
        ];
        $schema = Schema::connection('platform');
        if ($schema->hasColumn('fleet_owners', 'updated_at')) {
            $patch['updated_at'] = now();
        }
        if ($schema->hasColumn('fleet_owners', 'kyc_status')) {
            $patch['kyc_status'] = $approve ? 'approved' : 'rejected';
        }
        if ($approve && $schema->hasColumn('fleet_owners', 'verified_at')) {
            $patch['verified_at'] = now();
        }
        $this->db()->table('fleet_owners')->where('id', $id)->update($patch);
        if ($row->user_id) {
            $this->db()->table('users')->where('id', $row->user_id)->update([
                'status' => $approve ? 'ACTIVE' : 'PENDING',
                'updated_at' => now(),
            ]);
        }
        OrganizationAudit::record($actor, $approve ? 'fleet_owner.verified' : 'fleet_owner.rejected', 'fleet_owner', $id, null, $patch);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, int $id, array $data): void
    {
        $row = $this->find($actor, $id);
        $schema = Schema::connection('platform');
        $patch = [];
        foreach ([
            'trade_name' => $data['trade_name'] ?? null,
            'address' => $data['address'] ?? null,
            'gstin' => $data['gstin'] ?? null,
            'pan' => $data['pan'] ?? null,
            'company_type' => $data['company_type'] ?? null,
            'status' => $data['status'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'district_id' => $data['district_id'] ?? null,
            'franchise_id' => $data['franchise_id'] ?? null,
        ] as $column => $value) {
            if ($value === null || $value === '' || ! $schema->hasColumn('fleet_owners', $column)) {
                continue;
            }
            $patch[$column] = $value;
        }
        if ($schema->hasColumn('fleet_owners', 'updated_at')) {
            $patch['updated_at'] = now();
        }
        if ($patch !== []) {
            $this->db()->table('fleet_owners')->where('id', $id)->update($patch);
        }
        $userPatch = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
        if (! empty($data['password'])) {
            $userPatch['password_hash'] = Hash::make((string) $data['password']);
        }
        if ($row->user_id && $userPatch !== []) {
            $userPatch['updated_at'] = now();
            $this->db()->table('users')->where('id', $row->user_id)->update($userPatch);
        }
        OrganizationAudit::record($actor, 'fleet_owner.updated', 'fleet_owner', $id, null, $patch);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeDocument(User $actor, int $id, array $data, string $binary, string $mime, string $originalName): void
    {
        $row = $this->find($actor, $id);
        $schema = Schema::connection('platform');
        abort_unless($schema->hasColumn('fleet_owners', 'documents_json'), 500, 'Company documents are not available on this database.');
        $type = strtoupper(trim((string) ($data['type'] ?? '')));
        $allowed = ['GST', 'PAN', 'COMPANY_REG', 'ADDRESS_PROOF', 'BANK', 'TRADE_LICENSE'];
        abort_unless(in_array($type, $allowed, true), 422, 'Unknown company document type.');
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION) ?: '');
        if ($ext === '') {
            $ext = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'pdf') ? 'pdf' : 'jpg');
        }
        $path = 'fleet/'.$id.'/'.bin2hex(random_bytes(16)).'.'.$ext;
        abort_unless(app(KycFileStore::class)->write($path, $binary), 500, 'Could not save the attachment.');
        $docs = json_decode((string) ($row->documents_json ?? '[]'), true);
        $docs = is_array($docs) ? $docs : [];
        $docs = array_values(array_filter($docs, fn ($doc) => strtoupper((string) ($doc['type'] ?? '')) !== $type));
        $docs[] = [
            'type' => $type,
            'status' => 'pending',
            'storageKey' => $path,
            'originalName' => substr($originalName, 0, 180),
            'mime' => substr($mime, 0, 80),
            'uploadedAt' => now()->toIso8601String(),
        ];
        $patch = ['documents_json' => json_encode($docs)];
        if ($schema->hasColumn('fleet_owners', 'updated_at')) {
            $patch['updated_at'] = now();
        }
        $this->db()->table('fleet_owners')->where('id', $id)->update($patch);
        OrganizationAudit::record($actor, 'fleet_owner.document', 'fleet_owner', $id, null, ['type' => $type]);
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
