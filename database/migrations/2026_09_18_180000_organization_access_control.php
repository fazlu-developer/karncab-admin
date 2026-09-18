<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_permissions')) {
            Schema::create('user_permissions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('ability', 80);
                $table->timestamps();
                $table->unique(['user_id', 'ability']);
            });
        }

        if (! Schema::hasTable('state_head_assignments')) {
            Schema::create('state_head_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('state_id');
                $table->string('status', 24)->default('ACTIVE');
                $table->unsignedBigInteger('assigned_by')->nullable();
                $table->timestamp('assigned_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('old_data')->nullable();
                $table->json('new_data')->nullable();
                $table->timestamps();
                $table->index(['state_id', 'status']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('state_head_assignments');
        Schema::dropIfExists('user_permissions');
    }
};
