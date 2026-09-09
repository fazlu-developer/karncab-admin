<?php

namespace App\Repositories;

use App\Models\User;
use App\Platform\OperatorRole;

class OperatorRepository
{
    public function firstAdminExists(): bool
    {
        return User::query()
            ->whereIn('role', [OperatorRole::ADMIN, OperatorRole::SUPER_ADMIN])
            ->exists();
    }

    public function createOperator(array $attributes): User
    {
        $role = $this->firstAdminExists() ? OperatorRole::PENDING : OperatorRole::ADMIN;

        return User::query()->create([
            ...$attributes,
            'role' => $role,
            'status' => 'ACTIVE',
        ]);
    }
}
