<?php

namespace App\Policies;

class CustomerPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'customers';
    }
}
