<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'fleet_owner_id')) {
                $table->unsignedBigInteger('fleet_owner_id')->nullable()->after('district_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fleet_owner_id')) {
                $table->dropColumn('fleet_owner_id');
            }
        });
    }
};
