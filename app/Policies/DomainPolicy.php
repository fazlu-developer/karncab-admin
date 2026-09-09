<?php

namespace App\Policies;

use App\Models\User;

abstract class DomainPolicy
{
    abstract protected function domain(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->domain().'.view') || $user->can($this->domain().'.read');
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->can($this->domain().'.create') || $user->can($this->domain().'.write');
    }

    public function update(User $user): bool
    {
        return $user->can($this->domain().'.edit') || $user->can($this->domain().'.manage') || $user->can($this->domain().'.write');
    }

    public function delete(User $user): bool
    {
        return $user->can($this->domain().'.delete') || $user->can('platform.admin');
    }

    public function export(User $user): bool
    {
        return $user->can('reports.export') && $this->viewAny($user);
    }
}
