<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants') || Schema::hasColumn('tenants', 'status')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'disabled'])->default('pending');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'status')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
