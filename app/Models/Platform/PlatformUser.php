<?php

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlatformUser extends Model
{
    protected $connection = 'platform';

    protected $table = 'users';

    protected $guarded = ['id'];

    protected $hidden = ['password_hash'];

    public function driver(): HasOne
    {
        return $this->hasOne(PlatformDriver::class, 'user_id');
    }
}
