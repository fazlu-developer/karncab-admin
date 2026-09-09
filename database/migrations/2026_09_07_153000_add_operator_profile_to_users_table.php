<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive operator profile on existing Laravel users (physical table ops_users).
     * Does not touch Nest users, bookings, or other domain tables.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 32)->default('PENDING')->after('email');
            $table->string('status', 24)->default('ACTIVE')->after('role');
            $table->unsignedBigInteger('nest_user_id')->nullable()->after('status');
            $table->unsignedInteger('state_id')->nullable()->after('nest_user_id');
            $table->unsignedInteger('district_id')->nullable()->after('state_id');
            $table->index('role');
            $table->index('nest_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropIndex(['nest_user_id']);
            $table->dropColumn(['role', 'status', 'nest_user_id', 'state_id', 'district_id']);
        });
    }
};
