<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperatorRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(public User $operator) {}
}
