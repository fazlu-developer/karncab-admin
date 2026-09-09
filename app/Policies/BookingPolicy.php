<?php

namespace App\Policies;

class BookingPolicy extends DomainPolicy
{
    protected function domain(): string
    {
        return 'bookings';
    }
}
