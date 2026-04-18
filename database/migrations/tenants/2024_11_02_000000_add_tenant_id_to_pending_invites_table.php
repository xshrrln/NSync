<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('pending_invites') || Schema::hasColumn('pending_invites', 'tenant_id')) {
            return;
        }

        Schema::table('pending_invites', function (Blueprint $table) {
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pending_invites') || !Schema::hasColumn('pending_invites', 'tenant_id')) {
            return;
        }

        Schema::table('pending_invites', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};

