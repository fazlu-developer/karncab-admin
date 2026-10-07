<?php

namespace App\Services;

use App\Platform\OperatorRole;
use App\Platform\OpsNav;
use App\Platform\PlatformPermission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class AclCatalog
{
    /** @var list<string>|null */
    private static ?array $catalogMemo = null;

    /** @var array<string, list<string>> */
    private static array $roleMemo = [];

    private static bool $seeding = false;

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('acl_modules')
                && Schema::hasTable('acl_abilities')
                && Schema::hasTable('acl_role_permissions');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    public static function catalog(): array
    {
        if (! self::ready()) {
            return PlatformPermission::builtInCatalog();
        }
        self::seedIfEmpty();
        if (self::$catalogMemo !== null) {
            return self::$catalogMemo;
        }
        $rows = DB::table('acl_abilities')->orderBy('ability')->pluck('ability')->all();
        self::$catalogMemo = array_values(array_unique(array_map('strval', $rows)));

        return self::$catalogMemo !== [] ? self::$catalogMemo : PlatformPermission::builtInCatalog();
    }

    /**
     * @return list<string>|null  null = fall back to built-in matrix
     */
    public static function forRole(string $role): ?array
    {
        if (! self::ready()) {
            return null;
        }
        self::seedIfEmpty();
        if ($role === OperatorRole::SUPER_ADMIN) {
            return self::catalog();
        }
        if (isset(self::$roleMemo[$role])) {
            return self::$roleMemo[$role];
        }
        $exists = DB::table('acl_role_permissions')->where('role', $role)->exists();
        if (! $exists) {
            self::$roleMemo[$role] = PlatformPermission::builtInForRole($role);

            return self::$roleMemo[$role];
        }
        self::$roleMemo[$role] = array_values(array_unique(DB::table('acl_role_permissions')
            ->where('role', $role)
            ->orderBy('ability')
            ->pluck('ability')
            ->all()));

        return self::$roleMemo[$role];
    }

    /**
     * @return list<object>
     */
    public static function modules(): array
    {
        if (! self::ready()) {
            return [];
        }
        self::seedIfEmpty();

        return DB::table('acl_modules')->orderBy('sort_order')->orderBy('label')->get()->all();
    }

    /**
     * @return list<object>
     */
    public static function abilitiesFor(?int $moduleId = null): array
    {
        if (! self::ready()) {
            return [];
        }
        $q = DB::table('acl_abilities')->orderBy('sort_order')->orderBy('ability');
        if ($moduleId) {
            $q->where('module_id', $moduleId);
        }

        return $q->get()->all();
    }

    /**
     * Extra sidebar items for modules created in admin (not the built-in OpsNav keys).
     *
     * @return list<array{key: string, label: string, icon: string, group: string, ability: string}>
     */
    public static function customNavItems(): array
    {
        if (! self::ready()) {
            return [];
        }
        $known = [];
        foreach (OpsNav::coreItems() as $item) {
            $known[$item['key']] = true;
        }
        $out = [];
        foreach (self::modules() as $module) {
            if (! $module->active || ! $module->show_in_nav || isset($known[$module->key])) {
                continue;
            }
            $out[] = [
                'key' => (string) $module->key,
                'label' => (string) $module->label,
                'icon' => (string) ($module->icon ?: 'layout-grid'),
                'group' => (string) ($module->nav_group ?: 'Operations'),
                'ability' => $module->key.'.view',
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $actions
     */
    public static function createModule(string $key, string $label, string $group, string $icon, array $actions): int
    {
        $key = Str::slug($key, '_');
        $key = str_replace('-', '_', $key);
        abort_if($key === '' || strlen($key) < 2, 422, 'Module key must be at least 2 characters.');
        abort_if(! preg_match('/^[a-z][a-z0-9_]+$/', $key), 422, 'Use a lowercase key such as inventory or field_ops.');
        abort_if(DB::table('acl_modules')->where('key', $key)->exists(), 422, 'That module key already exists.');

        $actions = $actions !== [] ? $actions : ['view', 'create', 'edit', 'delete'];
        $id = DB::table('acl_modules')->insertGetId([
            'key' => $key,
            'label' => $label,
            'nav_group' => $group !== '' ? $group : 'Operations',
            'icon' => $icon !== '' ? $icon : 'layout-grid',
            'is_system' => false,
            'show_in_nav' => true,
            'active' => true,
            'sort_order' => 200 + (int) DB::table('acl_modules')->count(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sort = 0;
        foreach ($actions as $action) {
            $action = strtolower(trim($action));
            if ($action === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $action)) {
                continue;
            }
            $ability = $key.'.'.$action;
            DB::table('acl_abilities')->insert([
                'module_id' => $id,
                'ability' => $ability,
                'action' => $action,
                'label' => Str::headline($action),
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        self::flush();

        return (int) $id;
    }

    public static function updateModule(int $id, string $label, string $group, string $icon, bool $showInNav, bool $active): void
    {
        $row = DB::table('acl_modules')->where('id', $id)->first();
        abort_if(! $row, 404);
        DB::table('acl_modules')->where('id', $id)->update([
            'label' => $label,
            'nav_group' => $group,
            'icon' => $icon !== '' ? $icon : $row->icon,
            'show_in_nav' => $showInNav,
            'active' => $active,
            'updated_at' => now(),
        ]);
        self::flush();
    }

    public static function deleteModule(int $id): void
    {
        $row = DB::table('acl_modules')->where('id', $id)->first();
        abort_if(! $row, 404);
        abort_if($row->is_system, 422, 'Built-in modules cannot be deleted. Hide them from nav instead.');
        $abilities = DB::table('acl_abilities')->where('module_id', $id)->pluck('ability')->all();
        DB::table('acl_role_permissions')->whereIn('ability', $abilities)->delete();
        DB::table('acl_abilities')->where('module_id', $id)->delete();
        DB::table('acl_modules')->where('id', $id)->delete();
        self::flush();
    }

    /**
     * @param  list<string>  $abilities
     */
    public static function saveRole(string $role, array $abilities): void
    {
        abort_if($role === OperatorRole::SUPER_ADMIN, 422, 'Super Admin always has every permission.');
        abort_if(! in_array($role, OperatorRole::all(), true), 422, 'Unknown role.');
        $allowed = self::catalog();
        $clean = array_values(array_unique(array_filter(
            $abilities,
            fn ($ability) => is_string($ability) && in_array($ability, $allowed, true),
        )));
        DB::table('acl_role_permissions')->where('role', $role)->delete();
        $now = now();
        foreach ($clean as $ability) {
            DB::table('acl_role_permissions')->insert([
                'role' => $role,
                'ability' => $ability,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        self::flush();
    }

    public static function flush(): void
    {
        self::$catalogMemo = null;
        self::$roleMemo = [];
    }

    private static function seedIfEmpty(): void
    {
        if (self::$seeding || DB::table('acl_modules')->exists()) {
            return;
        }
        self::$seeding = true;
        try {
        $navByKey = [];
        foreach (OpsNav::coreItems() as $item) {
            $navByKey[$item['key']] = $item;
        }

        $grouped = [];
        foreach (PlatformPermission::builtInCatalog() as $ability) {
            $parts = explode('.', $ability, 2);
            $mod = $parts[0] ?: 'platform';
            $action = $parts[1] ?? 'access';
            $grouped[$mod][] = ['ability' => $ability, 'action' => $action];
        }

        $sort = 0;
        foreach ($grouped as $key => $abilities) {
            $nav = $navByKey[$key] ?? null;
            $moduleId = DB::table('acl_modules')->insertGetId([
                'key' => $key,
                'label' => $nav['label'] ?? Str::headline(str_replace('_', ' ', $key)),
                'nav_group' => $nav['group'] ?? 'System',
                'icon' => $nav['icon'] ?? 'layout-grid',
                'is_system' => true,
                'show_in_nav' => $nav !== null,
                'active' => true,
                'sort_order' => $sort++,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $aSort = 0;
            foreach ($abilities as $row) {
                DB::table('acl_abilities')->insert([
                    'module_id' => $moduleId,
                    'ability' => $row['ability'],
                    'action' => $row['action'],
                    'label' => Str::headline(str_replace('_', ' ', $row['action'])),
                    'sort_order' => $aSort++,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $now = now();
        foreach (OperatorRole::all() as $role) {
            if ($role === OperatorRole::SUPER_ADMIN) {
                continue;
            }
            foreach (PlatformPermission::builtInForRole($role) as $ability) {
                DB::table('acl_role_permissions')->insert([
                    'role' => $role,
                    'ability' => $ability,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
        self::flush();
        } finally {
            self::$seeding = false;
        }
    }
}
