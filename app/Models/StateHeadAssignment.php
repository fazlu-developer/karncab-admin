<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StateHeadAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'state_id',
        'status',
        'assigned_by',
        'assigned_at',
        'ended_at',
        'reason',
        'old_data',
        'new_data',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
            'old_data' => 'array',
            'new_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
