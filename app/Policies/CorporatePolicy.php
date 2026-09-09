<?php

namespace App\Policies;

class CorporatePolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'corporate';
    }
}
