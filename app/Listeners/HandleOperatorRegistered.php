<?php

namespace App\Listeners;

use App\Events\OperatorRegistered;
use App\Jobs\RecordOperatorAuditJob;
use App\Notifications\OperatorPendingNotification;
use App\Platform\OperatorRole;

class HandleOperatorRegistered
{
    public function handle(OperatorRegistered $event): void
    {
        RecordOperatorAuditJob::dispatch($event->operator->id, 'registered', [
            'role' => $event->operator->role,
        ]);

        if ($event->operator->role === OperatorRole::PENDING) {
            $event->operator->notify(new OperatorPendingNotification());
        }
    }
}
