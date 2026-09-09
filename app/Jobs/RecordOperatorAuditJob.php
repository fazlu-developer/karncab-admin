<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecordOperatorAuditJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $operatorId,
        public string $action,
        public array $context = [],
    ) {}

    public function handle(): void
    {
        $operator = User::query()->find($this->operatorId);
        Log::info('ops.audit', [
            'operator' => $operator?->email,
            'role' => $operator?->role,
            'action' => $this->action,
            'context' => $this->context,
        ]);
    }
}
