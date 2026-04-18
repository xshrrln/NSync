<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $table) {
                $table->id();
                $table->json('data')->nullable();
                $table->timestamps();
            });
        }

        // Seed a single settings row with sane defaults.
        if (!\DB::table('app_settings')->exists()) {
            \DB::table('app_settings')->insert([
                'data' => json_encode([
                    'default_plan' => 'free',
                    'notify_new_tenant' => true,
                    'maintenance_enabled' => false,
                    'maintenance_message' => 'All systems operational.',
                    'support_email' => null,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
