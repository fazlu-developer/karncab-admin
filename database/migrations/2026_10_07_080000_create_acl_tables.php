<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('acl_modules')) {
            Schema::create('acl_modules', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('label', 120);
                $table->string('nav_group', 80)->default('Operations');
                $table->string('icon', 40)->default('layout-grid');
                $table->boolean('is_system')->default(false);
                $table->boolean('show_in_nav')->default(true);
                $table->boolean('active')->default(true);
                $table->unsignedInteger('sort_order')->default(100);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('acl_abilities')) {
            Schema::create('acl_abilities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('module_id')->index();
                $table->string('ability', 80)->unique();
                $table->string('action', 40);
                $table->string('label', 120);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('acl_role_permissions')) {
            Schema::create('acl_role_permissions', function (Blueprint $table) {
                $table->id();
                $table->string('role', 40)->index();
                $table->string('ability', 80);
                $table->timestamps();
                $table->unique(['role', 'ability']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_role_permissions');
        Schema::dropIfExists('acl_abilities');
        Schema::dropIfExists('acl_modules');
    }
};
