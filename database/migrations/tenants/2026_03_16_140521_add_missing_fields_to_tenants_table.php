<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'address')) {
                $table->text('address')->nullable()->after('name');
            }
            if (!Schema::hasColumn('tenants', 'tenant_admin')) {
                $table->string('tenant_admin')->nullable()->after('address');
            }
            if (!Schema::hasColumn('tenants', 'tenant_admin_email')) {
                $table->string('tenant_admin_email')->nullable()->after('tenant_admin');
            }
            if (!Schema::hasColumn('tenants', 'start_date')) {
                $table->timestamp('start_date')->nullable()->after('tenant_admin_email');
            }
            if (!Schema::hasColumn('tenants', 'due_date')) {
                $table->timestamp('due_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('tenants', 'status')) {
                $table->enum('status', ['pending', 'approved', 'suspended', 'cancelled'])->default('pending')->after('due_date');
            }
            if (!Schema::hasColumn('tenants', 'theme')) {
                $table->json('theme')->nullable()->after('status'); // {"primary": "#hex", "secondary": "#hex"}
            }
            if (!Schema::hasColumn('tenants', 'actions')) {
                $table->json('actions')->nullable()->after('theme'); // [] for future actions
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'tenant_admin',
                'tenant_admin_email',
                'start_date',
                'due_date',
                'status',
                'theme',
                'actions'
            ]);
        });
    }
};
