<?php

namespace App\Services;

use App\Models\Platform\PlatformUser;
use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

final class OperatorProvisioner
{
    /** @var list<string> */
    public const CONSOLE_ROLES = [
        OperatorRole::SUPER_ADMIN,
        OperatorRole::ADMIN,
        OperatorRole::MANAGER,
        OperatorRole::STATE_HEAD,
        OperatorRole::DISTRICT_HEAD,
        OperatorRole::FRANCHISE,
        OperatorRole::FLEET_OWNER,
    ];

    /**
     * Create or update the admin login that matches this platform user.
     *
     * @param  list<string>  $abilities
     */
    /**
     * Put every platform State Head on the assignment page, including accounts created before console logins existed.
     */
    public function mirrorStateHeads(): void
    {
        $heads = PlatformUser::query()->where('role', OperatorRole::STATE_HEAD)->orderBy('name')->get();
        foreach ($heads as $platform) {
            $ops = $this->find($platform);
            if ($ops && $ops->role === OperatorRole::SUPER_ADMIN) {
                continue;
            }
            if (! $ops) {
                try {
                    $this->sync($platform, null, null, [], false);
                } catch (HttpException) {
                    // Leave the row unassigned when the state is already taken.
                }

                continue;
            }
            if ($ops->role !== OperatorRole::STATE_HEAD) {
                $ops->forceFill([
                    'role' => OperatorRole::STATE_HEAD,
                    'name' => $platform->name,
                    'status' => $platform->status ?: $ops->status,
                    'nest_user_id' => $platform->id,
                ])->save();
            } elseif (! $ops->nest_user_id) {
                $ops->forceFill(['nest_user_id' => $platform->id])->save();
            }
            $platformState = (int) ($platform->state_id ?? 0);
            if ($ops->status === 'ACTIVE' && $platformState > 0 && ! $ops->state_id) {
                try {
                    app(StateHeadSeat::class)->assign($ops->fresh(), $platformState, $ops, false);
                } catch (HttpException) {
                    // Shown without a state so an admin can transfer the seat.
                }
            }
        }
    }

    public function rememberPlatformState(User $operator, int $stateId): void
    {
        $query = PlatformUser::query()->where('role', OperatorRole::STATE_HEAD);
        $query->where(function ($inner) use ($operator) {
            $matched = false;
            if ($operator->nest_user_id) {
                $inner->where('id', $operator->nest_user_id);
                $matched = true;
            }
            if ($operator->email) {
                $matched ? $inner->orWhere('email', $operator->email) : $inner->where('email', $operator->email);
            }
        });
        $query->update([
            'state_id' => $stateId,
            'district_id' => null,
            'updated_at' => now(),
        ]);
    }

    /**
     * Create or update the admin login that matches this platform user.
     *
     * @param  list<string>  $abilities
     */
    public function sync(PlatformUser $platform, ?string $password, ?User $actor, array $abilities = [], bool $strictSeat = true): ?User
    {
        $role = strtoupper((string) $platform->role);
        $existing = $this->find($platform);
        if (! in_array($role, self::CONSOLE_ROLES, true)) {
            if ($existing && $existing->role !== OperatorRole::SUPER_ADMIN) {
                $existing->update(['status' => 'SUSPENDED']);
            }

            return $existing;
        }

        if ($existing && $existing->role === OperatorRole::SUPER_ADMIN && $role !== OperatorRole::SUPER_ADMIN) {
            abort(422, 'This email already belongs to a Super Admin login.');
        }

        $stateId = $platform->state_id ? (int) $platform->state_id : null;
        $districtId = $platform->district_id ? (int) $platform->district_id : null;
        if ($role === OperatorRole::STATE_HEAD) {
            $districtId = null;
        }

        $payload = [
            'name' => $platform->name,
            'email' => $platform->email,
            'role' => $role,
            'status' => $platform->status ?: 'ACTIVE',
            'nest_user_id' => $platform->id,
            'state_id' => $stateId,
            'district_id' => $districtId,
        ];
        if ($existing) {
            $existing->fill($payload);
            if ($password) {
                $existing->password = $password;
            }
            $existing->save();
            $operator = $existing;
        } else {
            $operator = new User($payload);
            $operator->password = $password ?: bin2hex(random_bytes(16));
            $operator->save();
        }

        if (in_array($role, [OperatorRole::ADMIN, OperatorRole::MANAGER], true)) {
            $operator->syncAbilities($abilities);
        }

        if ($role === OperatorRole::STATE_HEAD && $operator->status === 'ACTIVE' && $stateId) {
            try {
                app(StateHeadSeat::class)->assign($operator, $stateId, $actor ?? $operator, false);
            } catch (HttpException $exception) {
                if ($strictSeat) {
                    throw $exception;
                }
                $operator->update(['state_id' => null]);
            }
        }

        return $operator;
    }

    public function acceptPlatformPassword(string $email, string $password): bool
    {
        $platform = PlatformUser::query()->where('email', $email)->first();
        if (! $platform || ! in_array(strtoupper((string) $platform->role), self::CONSOLE_ROLES, true)) {
            return false;
        }
        $hash = (string) ($platform->password_hash ?? '');
        if ($hash === '') {
            return false;
        }
        try {
            $matches = Hash::check($password, $hash);
        } catch (Throwable) {
            return false;
        }
        if (! $matches) {
            return false;
        }
        $this->sync($platform, $password, null, []);

        return true;
    }

    private function find(PlatformUser $platform): ?User
    {
        $byNest = User::query()->where('nest_user_id', $platform->id)->first();
        if ($byNest) {
            return $byNest;
        }

        return User::query()->where('email', $platform->email)->first();
    }
}
