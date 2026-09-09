<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<string, mixed>  $fix
     */
    public function __construct(public readonly array $fix) {}
}
