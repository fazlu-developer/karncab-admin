<?php

namespace App\Models;

use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'nest_user_id',
        'state_id',
        'district_id',
        'fleet_owner_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'nest_user_id' => 'integer',
            'state_id' => 'integer',
            'district_id' => 'integer',
            'fleet_owner_id' => 'integer',
        ];
    }

    public function isPrivilegedOperator(): bool
    {
        return in_array($this->role, [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN], true);
    }

    public function isManager(): bool
    {
        return $this->role === OperatorRole::MANAGER;
    }

    public function isStateHead(): bool
    {
        return $this->role === OperatorRole::STATE_HEAD;
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StateHeadAssignment::class);
    }

    public function isFleetOwner(): bool
    {
        return $this->role === OperatorRole::FLEET_OWNER;
    }

    public function isAdvertiser(): bool
    {
        return $this->role === OperatorRole::ADVERTISER;
    }

    public function isCustomer(): bool
    {
        return in_array($this->role, [OperatorRole::CUSTOMER, OperatorRole::CORPORATE], true);
    }

    public function homeRoute(): string
    {
        if ($this->isStateHead()) {
            return 'state.dashboard';
        }
        if ($this->isFleetOwner()) {
            return 'fleet.dashboard';
        }
        if ($this->isAdvertiser()) {
            return 'ads.index';
        }
        if ($this->isCustomer()) {
            return 'customer.dashboard';
        }

        return 'dashboard';
    }

    public function hasPlatformAbility(string $ability): bool
    {
        if ($this->isPrivilegedOperator()) {
            return PlatformPermission::allows((string) $this->role, $ability);
        }

        $granted = PlatformPermission::forRole((string) $this->role);
        if ($this->isManager()) {
            $granted = array_values(array_unique(array_merge($granted, $this->assignedAbilities())));
        }

        $canonical = [];
        foreach ($granted as $item) {
            $canonical[] = $item;
            foreach (PlatformPermission::expand($item) as $alias) {
                $canonical[] = $alias;
            }
        }

        if (in_array($ability, $canonical, true)) {
            return true;
        }
        foreach (PlatformPermission::expand($ability) as $alias) {
            if (in_array($alias, $canonical, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function assignedAbilities(): array
    {
        if (! Schema::hasTable('user_permissions')) {
            return [];
        }

        return $this->permissions()->pluck('ability')->all();
    }

    /**
     * @param  list<string>  $abilities
     */
    public function syncAbilities(array $abilities): void
    {
        if (! Schema::hasTable('user_permissions')) {
            return;
        }
        $allowed = PlatformPermission::catalog();
        $clean = array_values(array_unique(array_filter(
            $abilities,
            fn (string $ability) => in_array($ability, $allowed, true),
        )));
        $this->permissions()->delete();
        foreach ($clean as $ability) {
            $this->permissions()->create(['ability' => $ability]);
        }
    }
}
