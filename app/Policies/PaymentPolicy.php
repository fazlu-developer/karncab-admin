<?php

namespace App\Policies;

class PaymentPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'payments';
    }
}
