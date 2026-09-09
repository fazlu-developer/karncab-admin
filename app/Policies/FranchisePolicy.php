<?php

namespace App\Policies;

class FranchisePolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'franchise';
    }
}
