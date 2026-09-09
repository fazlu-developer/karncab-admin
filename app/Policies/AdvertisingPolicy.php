<?php

namespace App\Policies;

class AdvertisingPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'advertising';
    }
}
