<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OperatorRoleAssigned
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $operator,
        public string $role,
        public ?User $actor = null,
    ) {}
}
