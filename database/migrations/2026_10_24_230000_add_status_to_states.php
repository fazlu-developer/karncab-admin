<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('platform');
        if (! $schema->hasTable('states') || $schema->hasColumn('states', 'status')) {
            return;
        }

        $schema->table('states', function (Blueprint $table) {
            $table->string('status', 24)->default('ACTIVE')->after('name');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('platform');
        if ($schema->hasTable('states') && $schema->hasColumn('states', 'status')) {
            $schema->table('states', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
