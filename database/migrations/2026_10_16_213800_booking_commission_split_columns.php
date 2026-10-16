<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('bookings')) {
            return;
        }
        $missing = array_values(array_filter(
            ['platform_commission_paise', 'district_head_commission_paise', 'fleet_commission_paise'],
            fn (string $column) => ! $schema->hasColumn('bookings', $column),
        ));
        if ($missing === []) {
            return;
        }
        $schema->table('bookings', function (Blueprint $table) use ($missing) {
            foreach ($missing as $column) {
                $table->bigInteger($column)->nullable();
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('bookings')) {
            return;
        }
        $schema->table('bookings', function (Blueprint $table) use ($schema) {
            foreach (['platform_commission_paise', 'district_head_commission_paise', 'fleet_commission_paise'] as $column) {
                if ($schema->hasColumn('bookings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
