<?php

namespace App\Livewire;

use App\Services\PlatformOpsService;
use Livewire\Component;
use Throwable;

class ApiHealthBadge extends Component
{
    public string $status = 'checking';

    public string $message = 'Checking platform database...';

    public function mount(PlatformOpsService $ops): void
    {
        $this->refreshStatus($ops);
    }

    public function refreshStatus(PlatformOpsService $ops): void
    {
        try {
            $ops->ping();
            $this->status = 'ok';
            $this->message = 'Connected to platform tables.';
        } catch (Throwable $exception) {
            $this->status = 'down';
            $this->message = 'Platform database unreachable: '.$exception->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.api-health-badge');
    }
}
