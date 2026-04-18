<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('boards')) {
            Schema::table('boards', function (Blueprint $table) {
                if (Schema::hasColumn('boards', 'name')) {
                    $table->text('name')->change();
                }

                if (Schema::hasColumn('boards', 'members')) {
                    $table->longText('members')->nullable()->change();
                }
            });
        }

        if (Schema::hasTable('stages')) {
            Schema::table('stages', function (Blueprint $table) {
                if (Schema::hasColumn('stages', 'name')) {
                    $table->text('name')->change();
                }
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                if (Schema::hasColumn('tasks', 'title')) {
                    $table->text('title')->change();
                }

                if (Schema::hasColumn('tasks', 'description')) {
                    $table->longText('description')->nullable()->change();
                }

                foreach (['assignees', 'labels', 'attachments', 'checklists'] as $column) {
                    if (Schema::hasColumn('tasks', $column)) {
                        $table->longText($column)->nullable()->change();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        // Irreversible safely because encrypted payloads may exceed original limits.
    }
};
