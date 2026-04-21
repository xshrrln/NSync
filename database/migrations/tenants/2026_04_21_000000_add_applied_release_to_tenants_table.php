<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'applied_release_version')) {
                $table->string('applied_release_version')->nullable();
            }

            if (! Schema::hasColumn('tenants', 'applied_release_at')) {
                $table->timestamp('applied_release_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'applied_release_at')) {
                $table->dropColumn('applied_release_at');
            }

            if (Schema::hasColumn('tenants', 'applied_release_version')) {
                $table->dropColumn('applied_release_version');
            }
        });
    }
};
