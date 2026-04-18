<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tenants')) {
            return;
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();

            // Requested fields
            $table->string('organization');
            $table->text('address')->nullable();
            $table->string('domain')->unique();
            $table->string('tenant_admin');
            $table->string('tenant_admin_email');
            $table->string('plan')->default('free');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->enum('status', ['pending', 'active', 'disabled'])->default('pending');

            // Compatibility fields already used by current app logic
            $table->string('name')->nullable();
            $table->string('database')->nullable()->unique();
            $table->json('theme')->nullable();
            $table->json('actions')->nullable();
            $table->json('billing_data')->nullable();

            $table->timestamps();

            $table->index('tenant_admin_email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

