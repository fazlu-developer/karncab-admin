<?php

namespace App\Http\Resources;

use App\Platform\PlatformPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class OperatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
            'nest_user_id' => $this->nest_user_id,
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'fleet_owner_id' => $this->fleet_owner_id,
            'permissions' => PlatformPermission::forRole((string) $this->role),
        ];
    }
}
