<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrganizationAudit
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function record(?User $actor, string $action, string $entityType, int|string $entityId, ?array $old = null, ?array $new = null, string $domain = 'organization'): void
    {
        if (! Schema::connection('platform')->hasTable('platform_audit_events')) {
            return;
        }

        $row = [
            'actor_user_id' => $actor?->nest_user_id ?: $actor?->id,
            'domain' => $domain,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => (string) $entityId,
            'created_at' => now(),
        ];
        if (Schema::connection('platform')->hasColumn('platform_audit_events', 'actor_role')) {
            $row['actor_role'] = $actor?->role;
        }
        if (Schema::connection('platform')->hasColumn('platform_audit_events', 'old_data')) {
            $row['old_data'] = $old ? json_encode($old) : null;
        }
        if (Schema::connection('platform')->hasColumn('platform_audit_events', 'new_data')) {
            $row['new_data'] = $new ? json_encode($new) : null;
        }

        DB::connection('platform')->table('platform_audit_events')->insert($row);
    }
}
