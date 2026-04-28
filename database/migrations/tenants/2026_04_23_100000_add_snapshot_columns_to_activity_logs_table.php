<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->string('task_title_snapshot')->nullable()->after('ip_address');
            $table->string('old_stage_name_snapshot')->nullable()->after('task_title_snapshot');
            $table->string('new_stage_name_snapshot')->nullable()->after('old_stage_name_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn([
                'task_title_snapshot',
                'old_stage_name_snapshot',
                'new_stage_name_snapshot',
            ]);
        });
    }
};
