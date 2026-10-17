<?php

namespace App\Services;

use App\Models\Platform\PlatformUser;
use App\Models\User;
use App\Platform\OperatorRole;
use Illuminate\Support\Facades\Hash;
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
    public function sync(PlatformUser $platform, ?string $password, ?User $actor, array $abilities = []): ?User
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
            app(StateHeadSeat::class)->assign($operator, $stateId, $actor ?? $operator, false);
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
