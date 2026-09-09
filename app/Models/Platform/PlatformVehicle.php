<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformVehicle extends Model
{
    protected $connection = 'platform';

    protected $table = 'vehicles';

    protected $guarded = ['id'];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(PlatformDriver::class, 'driver_id');
    }
}
