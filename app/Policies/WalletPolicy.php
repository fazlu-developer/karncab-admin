<?php

namespace App\Policies;

class WalletPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'wallets';
    }
}
