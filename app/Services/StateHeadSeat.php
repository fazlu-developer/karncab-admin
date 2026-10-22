<?php

namespace App\Services;

use App\Models\Platform\PlatformUser;
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

        $live = null;
        foreach ($query->orderBy('id')->get() as $holder) {
            if ($this->platformAccountExists($holder)) {
                $live = $holder;
                break;
            }
            $this->releaseStale($holder);
        }

        return $live;
    }

    private function platformAccountExists(User $holder): bool
    {
        $accounts = PlatformUser::query()->where('role', OperatorRole::STATE_HEAD);
        if ($holder->nest_user_id && (clone $accounts)->whereKey($holder->nest_user_id)->exists()) {
            return true;
        }

        return $holder->email !== null
            && $holder->email !== ''
            && (clone $accounts)->where('email', $holder->email)->exists();
    }

    private function releaseStale(User $holder): void
    {
        $this->closeOpenRows((int) $holder->id, 'Platform account no longer exists');
        $holder->update(['state_id' => null, 'status' => 'SUSPENDED']);
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
