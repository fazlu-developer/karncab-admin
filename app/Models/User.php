<?php

namespace App\Models;

use App\Platform\OperatorRole;
use App\Platform\PlatformPermission;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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

    public function isStateHead(): bool
    {
        return $this->role === OperatorRole::STATE_HEAD;
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
        return PlatformPermission::allows((string) $this->role, $ability);
    }
}
