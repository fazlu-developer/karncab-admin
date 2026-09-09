<?php

namespace App\Policies;

class UserPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'users';
    }
}
