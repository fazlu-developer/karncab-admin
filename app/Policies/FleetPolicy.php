<?php

namespace App\Policies;

class FleetPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'fleet';
    }
}
