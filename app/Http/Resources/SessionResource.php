<?php

namespace App\Http\Resources;

use App\Platform\PlatformPermission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = (string) $this->resource->role;
        $permissions = PlatformPermission::forRole($role);

        return [
            'operator' => new OperatorResource($this->resource),
            'domains' => collect(PlatformPermission::DOMAINS)->map(fn (string $domain) => [
                'key' => $domain,
                'canRead' => PlatformPermission::allows($role, "{$domain}.view") || PlatformPermission::allows($role, "{$domain}.read"),
                'canWrite' => PlatformPermission::allows($role, "{$domain}.edit")
                    || PlatformPermission::allows($role, "{$domain}.create")
                    || PlatformPermission::allows($role, "{$domain}.manage")
                    || PlatformPermission::allows($role, "{$domain}.write"),
            ])->values(),
            'permissions' => $permissions,
            'apiVersion' => '1',
            'dataSource' => 'laravel',
        ];
    }
}
