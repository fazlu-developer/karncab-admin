<?php

namespace App\Services;

use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StateHeadSeat
{
    public const CONFLICT = 'This state already has an active State Head.';

    public function occupiedBy(int $stateId, ?int $exceptUserId = null): ?User
    {
        $query = User::query()
            ->where('role', OperatorRole::STATE_HEAD)
            ->where('status', 'ACTIVE')
            ->where('state_id', $stateId);
        if ($exceptUserId) {
            $query->where('id', '!=', $exceptUserId);
        }

        return $query->orderBy('id')->first();
    }

    public function assertAvailable(int $stateId, ?int $exceptUserId = null): void
    {
        $holder = $this->occupiedBy($stateId, $exceptUserId);
        if ($holder) {
            throw new HttpException(409, self::CONFLICT);
        }
    }

    /**
     * @param  array<string, mixed>  $old
     */
    public function assign(User $head, int $stateId, User $actor, bool $transfer = false): void
    {
        abort_unless($head->role === OperatorRole::STATE_HEAD, 422, 'Only State Head accounts can be assigned a state.');
        abort_unless($head->status === 'ACTIVE', 422, 'Only an active State Head can hold a state.');

        $holder = $this->occupiedBy($stateId, (int) $head->id);
        if ($holder) {
            if (! $transfer) {
                throw new HttpException(409, self::CONFLICT);
            }
            $this->endAssignment($holder, $actor, 'Transferred to '.$head->name, true);
        }

        $old = ['state_id' => $head->state_id, 'status' => $head->status];
        $this->closeOpenRows((int) $head->id);
        $head->update(['state_id' => $stateId, 'district_id' => null]);
        $this->openRow($head, $stateId, $actor, $old);
        OrganizationAudit::record($actor, 'state_head.changed', 'state_head', $head->id, $old, [
            'state_id' => $stateId,
            'user_id' => $head->id,
        ]);
    }

    public function endAssignment(User $head, User $actor, string $reason, bool $disable = false): void
    {
        $old = ['state_id' => $head->state_id, 'status' => $head->status];
        $this->closeOpenRows((int) $head->id, $reason);
        $payload = ['state_id' => null];
        if ($disable) {
            $payload['status'] = 'SUSPENDED';
        }
        $head->update($payload);
        OrganizationAudit::record($actor, 'state_head.ended', 'state_head', $head->id, $old, $payload);
    }

    private function openRow(User $head, int $stateId, User $actor, array $old): void
    {
        if (! Schema::hasTable('state_head_assignments')) {
            return;
        }
        $head->assignments()->create([
            'state_id' => $stateId,
            'status' => 'ACTIVE',
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
            'old_data' => $old,
            'new_data' => ['state_id' => $stateId],
        ]);
    }

    private function closeOpenRows(int $userId, ?string $reason = null): void
    {
        if (! Schema::hasTable('state_head_assignments')) {
            return;
        }
        User::query()->find($userId)?->assignments()
            ->where('status', 'ACTIVE')
            ->update([
                'status' => 'ENDED',
                'ended_at' => now(),
                'reason' => $reason,
                'updated_at' => now(),
            ]);
    }
}
