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
        Schema::table('tasks', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('tasks', 'assignees')) {
                $table->json('assignees')->nullable()->after('description');
            }
            if (!Schema::hasColumn('tasks', 'labels')) {
                $table->json('labels')->nullable()->after('assignees');
            }
            if (!Schema::hasColumn('tasks', 'due_date')) {
                $table->date('due_date')->nullable()->after('labels');
            }
            if (!Schema::hasColumn('tasks', 'attachments')) {
                $table->json('attachments')->nullable()->after('due_date');
            }
            if (!Schema::hasColumn('tasks', 'checklists')) {
                $table->json('checklists')->nullable()->after('attachments');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            //
        });
    }
};
