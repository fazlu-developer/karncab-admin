<?php

namespace App\Support;

use Carbon\Carbon;

class IndiaCarbon extends Carbon
{
    public function __toString(): string
    {
        return $this->copy()->timezone('Asia/Kolkata')->format('d/m/Y h:i A');
    }
}
