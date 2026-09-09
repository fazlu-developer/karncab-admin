<?php

namespace App\Policies;

class TravelPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'travel';
    }
}
