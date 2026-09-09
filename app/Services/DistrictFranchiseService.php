<?php

namespace App\Services;

use App\Models\User;
use App\Platform\ExclusiveDistrictSeat;
use App\Platform\OperatorRole;
use App\Platform\TerritoryScope;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DistrictFranchiseService
{
    public const CONFLICT = 'This district already has an active District Head or exclusive franchise.';

    /**
     * @param  array<string, mixed>  $query
     * @return list<array<string, mixed>>
     */
    public function list(User $operator, array $query = []): array
    {
        abort_unless($operator->can('franchise.view'), 403);
        $q = $this->scoped($operator);
        if (! empty($query['status'])) {
            $q->where('franchises.status', $query['status']);
        }
        if (! empty($query['districtId'])) {
            $q->where('franchises.district_id', (int) $query['districtId']);
        }

        return $q->orderByDesc('franchises.id')->limit(200)->get()->map(fn ($row) => $this->present($row))->all();
    }

    public function one(User $operator, int $id): array
    {
        abort_unless($operator->can('franchise.view'), 403);
        $row = $this->scoped($operator)->where('franchises.id', $id)->first();
        abort_if($row === null, 404, 'Franchise not found');

        $payload = $this->present($row);
        $payload['documents'] = $this->db()->table('franchise_documents')->where('franchise_id', $id)->orderBy('id')->get()->toArray();
        $payload['agreements'] = $this->db()->table('franchise_agreements')->where('franchise_id', $id)->orderByDesc('id')->get()->toArray();
        $payload['fees'] = $this->db()->table('franchise_fees')->where('franchise_id', $id)->orderByDesc('id')->get()->toArray();
        $payload['renewals'] = $this->db()->table('franchise_renewals')->where('franchise_id', $id)->orderByDesc('id')->get()->toArray();
        $payload['events'] = $this->db()->table('franchise_events')->where('franchise_id', $id)->orderByDesc('id')->limit(40)->get()->toArray();
        $payload['wallet'] = $this->walletFor((int) $row->owner_user_id, (string) $row->kind);
        $payload['performance'] = $this->performance((int) $row->district_id);

        return $payload;
    }

    /**
     * Application never claims the exclusive seat. Activation does.
     *
     * @param  array<string, mixed>  $data
     */
    public function apply(User $operator, array $data): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $kind = $data['kind'] ?? 'EXCLUSIVE_FRANCHISE';
        abort_unless(in_array($kind, ExclusiveDistrictSeat::KINDS, true), 422, 'Invalid franchise kind.');
        $district = $this->requireDistrict((int) $data['district_id'], $operator);
        $ownerId = $this->resolveOwner($operator, $data);

        $id = $this->db()->table('franchises')->insertGetId([
            'district_id' => $district->id,
            'state_id' => $district->state_id,
            'owner_user_id' => $ownerId,
            'kind' => $kind,
            'status' => 'APPLIED',
            'active_district_key' => null,
            'trade_name' => $data['trade_name'],
            'gstin' => $data['gstin'] ?? null,
            'pan' => $data['pan'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'kyc_status' => 'pending',
            'agreement_status' => 'unsigned',
            'fee_amount_paise' => (int) ($data['fee_amount_paise'] ?? 0),
            'commission_percent' => $data['commission_percent'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ((int) ($data['fee_amount_paise'] ?? 0) > 0) {
            $this->db()->table('franchise_fees')->insert([
                'franchise_id' => $id,
                'kind' => 'application',
                'amount_paise' => (int) $data['fee_amount_paise'],
                'status' => 'due',
                'created_at' => now(),
            ]);
        }
        $this->record($id, $operator, 'apply', null, 'APPLIED', 'Territory district '.$district->id);

        return $this->one($operator, $id);
    }

    public function setTerritory(User $operator, int $id, int $districtId): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $row = $this->requireRow($operator, $id);
        $district = $this->requireDistrict($districtId, $operator);
        if (ExclusiveDistrictSeat::holdsSeat((string) $row->status) && (int) $row->district_id !== $districtId) {
            $this->claimSeatOrFail($id, $districtId, (int) $row->id);
        }
        $this->db()->table('franchises')->where('id', $id)->update([
            'district_id' => $district->id,
            'state_id' => $district->state_id,
            'active_district_key' => ExclusiveDistrictSeat::holdsSeat((string) $row->status)
                ? ExclusiveDistrictSeat::key($districtId)
                : null,
            'updated_at' => now(),
        ]);
        $this->record($id, $operator, 'territory', (string) $row->status, (string) $row->status, 'District '.$districtId);

        return $this->one($operator, $id);
    }

    public function lifecycle(User $operator, int $id, string $status, ?string $reason = null): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        abort_unless(in_array($status, ExclusiveDistrictSeat::STATUSES, true), 422, 'Unknown franchise status.');
        $row = $this->requireRow($operator, $id);
        abort_unless(ExclusiveDistrictSeat::canTransition((string) $row->status, $status), 422, "Cannot move from {$row->status} to {$status}.");

        $hold = ExclusiveDistrictSeat::holdsSeat($status);
        if ($hold) {
            $this->assertSeatFree((int) $row->district_id, (int) $row->id);
        }

        try {
            $this->db()->transaction(function () use ($row, $id, $status, $hold, $reason, $operator) {
                $this->db()->table('franchises')->where('id', $id)->update([
                    'status' => $status,
                    'active_district_key' => $hold ? ExclusiveDistrictSeat::key((int) $row->district_id) : null,
                    'terminated_at' => $status === 'TERMINATED' ? now() : $row->terminated_at,
                    'termination_reason' => $reason ?? $row->termination_reason,
                    'starts_on' => $hold && ! $row->starts_on ? now() : $row->starts_on,
                    'updated_at' => now(),
                ]);
                if ($hold) {
                    $this->activateOwner((int) $row->owner_user_id, (string) $row->kind, (int) $row->district_id, (int) $row->state_id);
                    $this->ensureWallet((int) $row->owner_user_id, (string) $row->kind);
                }
                $this->record($id, $operator, 'lifecycle', (string) $row->status, $status, $reason);
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                abort(409, self::CONFLICT);
            }
            throw $exception;
        }

        if ($status === 'ACTIVE') {
            app(NotificationService::class)->dispatch((int) $row->owner_user_id, 'franchise_approval', [
                'ref' => (string) $id,
            ], ['entity' => ['type' => 'franchise', 'id' => (string) $id]]);
        }

        return $this->one($operator, $id);
    }

    public function setKyc(User $operator, int $id, string $kycStatus, ?string $reason = null): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        abort_unless(in_array($kycStatus, ['pending', 'submitted', 'verified', 'rejected'], true), 422, 'Invalid KYC status.');
        $this->requireRow($operator, $id);
        $this->db()->table('franchises')->where('id', $id)->update([
            'kyc_status' => $kycStatus,
            'updated_at' => now(),
        ]);
        $this->record($id, $operator, 'kyc', null, null, $reason ?? $kycStatus);

        return $this->one($operator, $id);
    }

    public function signAgreement(User $operator, int $id, string $version = 'v1'): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $this->db()->table('franchise_agreements')->insert([
            'franchise_id' => $id,
            'version' => $version,
            'title' => 'KarnaCab exclusive district franchise agreement',
            'signed_at' => now(),
            'status' => 'signed',
            'created_at' => now(),
        ]);
        $this->db()->table('franchises')->where('id', $id)->update([
            'agreement_status' => 'signed',
            'updated_at' => now(),
        ]);
        $this->record($id, $operator, 'agreement', null, null, $version);

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addFee(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $this->db()->table('franchise_fees')->insert([
            'franchise_id' => $id,
            'kind' => $data['kind'],
            'amount_paise' => (int) $data['amount_paise'],
            'status' => 'due',
            'due_on' => $data['due_on'] ?? null,
            'note' => $data['note'] ?? null,
            'created_at' => now(),
        ]);

        return $this->one($operator, $id);
    }

    public function payFee(User $operator, int $id, int $feeId): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $updated = $this->db()->table('franchise_fees')->where('id', $feeId)->where('franchise_id', $id)->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        abort_if($updated === 0, 404, 'Fee not found');
        $due = $this->db()->table('franchise_fees')->where('franchise_id', $id)->where('status', 'due')->count();
        if ($due === 0) {
            $this->db()->table('franchises')->where('id', $id)->update(['fee_paid_at' => now(), 'updated_at' => now()]);
        }

        return $this->one($operator, $id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestRenewal(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $this->db()->table('franchise_renewals')->insert([
            'franchise_id' => $id,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $this->record($id, $operator, 'renewal', null, null, null);

        return $this->one($operator, $id);
    }

    public function setCommission(User $operator, int $id, float $percent): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $this->db()->table('franchises')->where('id', $id)->update([
            'commission_percent' => $percent,
            'updated_at' => now(),
        ]);

        return $this->one($operator, $id);
    }

    public function reassign(User $operator, int $id, int $districtId): array
    {
        return $this->setTerritory($operator, $id, $districtId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addDocument(User $operator, int $id, array $data): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        $this->requireRow($operator, $id);
        $this->db()->table('franchise_documents')->insert([
            'franchise_id' => $id,
            'type' => $data['type'],
            'status' => $data['status'] ?? 'pending',
            'storage_key' => $data['storage_key'],
            'original_name' => $data['original_name'] ?? null,
            'mime' => $data['mime'] ?? 'application/octet-stream',
            'size_bytes' => (int) ($data['size_bytes'] ?? 0),
            'checksum_sha256' => $data['checksum_sha256'] ?? str_repeat('0', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->record($id, $operator, 'kyc-document', null, null, $data['type']);

        return $this->one($operator, $id);
    }

    public function decideRenewal(User $operator, int $id, int $renewalId, string $status): array
    {
        abort_unless($operator->can('franchise.manage'), 403);
        abort_unless(in_array($status, ['approved', 'rejected'], true), 422, 'Renewal must be approved or rejected.');
        $this->requireRow($operator, $id);
        $updated = $this->db()->table('franchise_renewals')
            ->where('id', $renewalId)
            ->where('franchise_id', $id)
            ->update(['status' => $status]);
        abort_if($updated === 0, 404, 'Renewal not found');
        $this->record($id, $operator, 'renewal-decision', null, null, $status);

        return $this->one($operator, $id);
    }

    /**
     * @return list<array{id: int, name: string, stateId: int}>
     */
    public function districtChoices(User $operator): array
    {
        abort_unless($operator->can('franchise.view'), 403);
        $q = $this->db()->table('districts')->orderBy('name');
        if ($operator->isStateHead() && $operator->state_id) {
            $q->where('state_id', $operator->state_id);
        } elseif (in_array($operator->role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true) && $operator->district_id) {
            $q->where('id', $operator->district_id);
        }

        return $q->get()->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'stateId' => (int) $row->state_id,
        ])->all();
    }

    private function assertSeatFree(int $districtId, int $selfId): void
    {
        $existing = $this->db()->table('franchises')
            ->where('district_id', $districtId)
            ->where('status', 'ACTIVE')
            ->where('id', '!=', $selfId)
            ->value('id');
        abort_if($existing !== null, 409, self::CONFLICT);
    }

    private function claimSeatOrFail(int $id, int $districtId, int $selfId): void
    {
        $this->assertSeatFree($districtId, $selfId);
        try {
            $this->db()->table('franchises')->where('id', $id)->update([
                'active_district_key' => ExclusiveDistrictSeat::key($districtId),
                'updated_at' => now(),
            ]);
        } catch (QueryException $exception) {
            if ($this->isUniqueConflict($exception)) {
                abort(409, self::CONFLICT);
            }
            throw $exception;
        }
    }

    private function activateOwner(int $ownerUserId, string $kind, int $districtId, int $stateId): void
    {
        $role = $kind === 'DISTRICT_HEAD' ? 'DISTRICT_HEAD' : 'FRANCHISE';
        $this->db()->table('users')->where('id', $ownerUserId)->whereNotIn('role', ['ADMIN', 'SUPER_ADMIN'])->update([
            'role' => $role,
            'district_id' => $districtId,
            'state_id' => $stateId,
            'status' => 'ACTIVE',
            'updated_at' => now(),
        ]);
    }

    private function ensureWallet(int $ownerUserId, string $kind): void
    {
        $ownerType = $kind === 'DISTRICT_HEAD' ? 'DISTRICT_HEAD' : 'FRANCHISE';
        $exists = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $ownerUserId)->exists();
        if (! $exists) {
            $this->db()->table('wallets')->insert([
                'owner_type' => $ownerType,
                'owner_user_id' => $ownerUserId,
                'balance_paise' => 0,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function performance(int $districtId): array
    {
        return [
            'vehicles' => $this->db()->table('vehicles')->where('district_id', $districtId)->count(),
            'completedTrips' => $this->db()->table('bookings')->where('district_id', $districtId)->where('status', 'COMPLETED')->count(),
            'grossRupees' => ((int) $this->db()->table('bookings')->where('district_id', $districtId)->where('status', 'COMPLETED')->sum('quote_paise')) / 100,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function walletFor(int $ownerUserId, string $kind): ?array
    {
        $ownerType = $kind === 'DISTRICT_HEAD' ? 'DISTRICT_HEAD' : 'FRANCHISE';
        $row = $this->db()->table('wallets')->where('owner_type', $ownerType)->where('owner_user_id', $ownerUserId)->first();
        if (! $row) {
            return null;
        }

        return [
            'ownerType' => $row->owner_type,
            'balancePaise' => (int) $row->balance_paise,
            'balanceRupees' => ((int) $row->balance_paise) / 100,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveOwner(User $operator, array $data): int
    {
        if (! empty($data['owner_user_id'])) {
            $owner = $this->db()->table('users')->where('id', (int) $data['owner_user_id'])->first();
            abort_if($owner === null, 404, 'Owner user not found');

            return (int) $owner->id;
        }
        if (! empty($data['name']) && ! empty($data['email']) && ! empty($data['password'])) {
            return (int) $this->db()->table('users')->insertGetId([
                'role' => ($data['kind'] ?? '') === 'DISTRICT_HEAD' ? 'DISTRICT_HEAD' : 'FRANCHISE',
                'status' => 'PENDING',
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'phone' => $data['phone'] ?? null,
                'password_hash' => Hash::make($data['password']),
                'state_id' => null,
                'district_id' => (int) $data['district_id'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        abort(422, 'Provide owner_user_id or name, email and password.');
    }

    private function requireDistrict(int $districtId, User $operator): object
    {
        $district = $this->db()->table('districts')->where('id', $districtId)->first();
        abort_if($district === null, 422, 'District territory must exist.');
        if ($operator->isStateHead()) {
            abort_unless((int) $district->state_id === (int) $operator->state_id, 422, 'District is outside the assigned state.');
        }
        if (in_array($operator->role, [OperatorRole::DISTRICT_HEAD, OperatorRole::FRANCHISE], true) && $operator->district_id) {
            abort_unless((int) $district->id === (int) $operator->district_id, 403, 'Outside assigned district.');
        }

        return $district;
    }

    private function requireRow(User $operator, int $id): object
    {
        $row = $this->scoped($operator)->where('franchises.id', $id)->first();
        abort_if($row === null, 404, 'Franchise not found');

        return $row;
    }

    private function scoped(User $operator)
    {
        $q = $this->db()->table('franchises');
        TerritoryScope::applyFranchises($q, $operator);

        return $q;
    }

    private function record(int $franchiseId, User $operator, string $action, ?string $from, ?string $to, ?string $note): void
    {
        $this->db()->table('franchise_events')->insert([
            'franchise_id' => $franchiseId,
            'actor_user_id' => $operator->nest_user_id ?: $operator->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'kind' => $row->kind,
            'status' => $row->status,
            'tradeName' => $row->trade_name,
            'districtId' => (int) $row->district_id,
            'stateId' => (int) $row->state_id,
            'ownerUserId' => (int) $row->owner_user_id,
            'kycStatus' => $row->kyc_status ?? 'pending',
            'agreementStatus' => $row->agreement_status ?? 'unsigned',
            'commissionPercent' => (float) ($row->commission_percent ?? 0),
            'feeAmountPaise' => (int) ($row->fee_amount_paise ?? 0),
            'exclusiveSeat' => ExclusiveDistrictSeat::holdsSeat((string) $row->status),
            'activeDistrictKey' => $row->active_district_key,
            'terminationReason' => $row->termination_reason ?? null,
        ];
    }

    private function isUniqueConflict(QueryException $exception): bool
    {
        $code = (string) $exception->getCode();
        $message = $exception->getMessage();

        return $code === '23000' || str_contains($message, 'UNIQUE') || str_contains($message, 'unique');
    }

    private function db()
    {
        return DB::connection('platform');
    }
}
