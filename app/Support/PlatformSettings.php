<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlatformSettings
{
    public static function get(string $key, ?string $default = null): ?string
    {
        if (! Schema::connection('platform')->hasTable('system_settings')) {
            return $default;
        }
        $value = DB::connection('platform')->table('system_settings')->where('key', $key)->value('value');

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function json(string $key, array $default = []): array
    {
        $raw = self::get($key);
        if (! $raw) {
            return $default;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public static function put(string $key, string $value): void
    {
        if (! Schema::connection('platform')->hasTable('system_settings')) {
            return;
        }
        $db = DB::connection('platform');
        $row = ['key' => $key, 'value' => $value];
        if (Schema::connection('platform')->hasColumn('system_settings', 'updated_at')) {
            $row['updated_at'] = now();
        }
        $exists = $db->table('system_settings')->where('key', $key)->first();
        if ($exists) {
            $db->table('system_settings')->where('key', $key)->update(array_diff_key($row, ['key' => true]));

            return;
        }
        if (Schema::connection('platform')->hasColumn('system_settings', 'created_at')) {
            $row['created_at'] = now();
        }
        $db->table('system_settings')->insert($row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function filter(string $table, array $row): array
    {
        return array_filter(
            $row,
            fn ($key) => Schema::connection('platform')->hasColumn($table, $key),
            ARRAY_FILTER_USE_KEY,
        );
    }
}
