<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('fleet_owners') && ! $schema->hasTable('drivers')) {
            return;
        }

        if ($schema->hasTable('districts')) {
            $schema->table('districts', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('districts', 'code')) {
                    $table->string('code', 32)->nullable();
                }
                if (! $schema->hasColumn('districts', 'status')) {
                    $table->string('status', 24)->default('ACTIVE');
                }
            });
        }

        if ($schema->hasTable('fleet_owners')) {
            $schema->table('fleet_owners', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('fleet_owners', 'state_id')) {
                    $table->unsignedInteger('state_id')->nullable();
                }
                if (! $schema->hasColumn('fleet_owners', 'district_id')) {
                    $table->unsignedInteger('district_id')->nullable();
                }
                if (! $schema->hasColumn('fleet_owners', 'franchise_id')) {
                    $table->unsignedBigInteger('franchise_id')->nullable();
                }
                if (! $schema->hasColumn('fleet_owners', 'status')) {
                    $table->string('status', 24)->default('ACTIVE');
                }
                if (! $schema->hasColumn('fleet_owners', 'address')) {
                    $table->string('address', 255)->nullable();
                }
            });
        }

        if ($schema->hasTable('drivers')) {
            $schema->table('drivers', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('drivers', 'driver_type')) {
                    $table->string('driver_type', 32)->nullable();
                }
                if (! $schema->hasColumn('drivers', 'state_id')) {
                    $table->unsignedInteger('state_id')->nullable();
                }
                if (! $schema->hasColumn('drivers', 'district_id')) {
                    $table->unsignedInteger('district_id')->nullable();
                }
            });
            try {
                DB::connection('platform')->table('drivers')->whereNull('driver_type')->whereNotNull('fleet_owner_id')->update(['driver_type' => 'fleet_driver']);
                DB::connection('platform')->table('drivers')->whereNull('driver_type')->whereNull('fleet_owner_id')->update(['driver_type' => 'individual_driver']);
            } catch (\Throwable) {
            }
        }

        if ($schema->hasTable('vehicles')) {
            $schema->table('vehicles', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('vehicles', 'state_id')) {
                    $table->unsignedInteger('state_id')->nullable();
                }
                if (! $schema->hasColumn('vehicles', 'individual_driver_id')) {
                    $table->unsignedBigInteger('individual_driver_id')->nullable();
                }
            });
        }

        if ($schema->hasTable('platform_audit_events')) {
            $schema->table('platform_audit_events', function (Blueprint $table) use ($schema) {
                if (! $schema->hasColumn('platform_audit_events', 'old_data')) {
                    $table->json('old_data')->nullable();
                }
                if (! $schema->hasColumn('platform_audit_events', 'new_data')) {
                    $table->json('new_data')->nullable();
                }
                if (! $schema->hasColumn('platform_audit_events', 'actor_role')) {
                    $table->string('actor_role', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
    }
};
