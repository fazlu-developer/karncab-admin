<?php

namespace App\Policies;

use App\Models\User;

class DriverPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'drivers';
    }

    public function approve(User $user): bool
    {
        return $user->can('drivers.approve');
    }
}
