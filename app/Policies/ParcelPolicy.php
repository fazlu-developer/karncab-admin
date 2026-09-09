<?php

namespace App\Policies;

class ParcelPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'parcels';
    }
}
