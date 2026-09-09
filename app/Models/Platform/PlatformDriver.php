<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformDriver extends Model
{
    protected $connection = 'platform';

    protected $table = 'drivers';

    protected $guarded = ['id'];

    protected $casts = [
        'online' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(PlatformUser::class, 'user_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PlatformDriverDocument::class, 'driver_id');
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(PlatformVehicle::class, 'driver_id');
    }
}
