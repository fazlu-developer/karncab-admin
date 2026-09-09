<?php

namespace App\Policies;

class VehiclePolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'vehicles';
    }
}
