<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use App\Events\TaskUpdated;
use Illuminate\Support\Facades\Log;

class Task extends Model
{
    use UsesTenantConnection, LogsActivity;

    protected $connection = 'tenant';

    protected $fillable = ['tenant_id', 'title', 'description', 'assignees', 'labels', 'due_date', 'attachments', 'checklists', 'stage_id', 'board_id', 'user_id', 'position'];

    protected $casts = [
        'title' => 'encrypted',
        'description' => 'encrypted',
        'assignees' => 'encrypted:array',
        'labels' => 'encrypted:array',
        'attachments' => 'encrypted:array',
        'checklists' => 'encrypted:array',
        'due_date' => 'date',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: A task belongs to a stage (column).
     */
    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }

    /**
     * Relationship: A task belongs to a board.
     */
    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'stage_id', 'user_id', 'position'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Task has been {$eventName}");
    }

    public static function booted()
    {
        static::addGlobalScope('tenant', function ($builder) {
            $tenant = app()->has('currentTenant') ? app('currentTenant') : null;
            $connection = $builder->getQuery()->getConnection()->getName();
            $table = $builder->getModel()->getTable();
            $hasTenantId = Schema::connection($connection)->hasColumn($table, 'tenant_id');

            if ($tenant && $hasTenantId) {
                $builder->where('tenant_id', $tenant->id);
            }
        });

        static::updated(function (Task $task) {
            try {
                TaskUpdated::dispatch($task);
            } catch (\Throwable $e) {
                Log::warning('Task update broadcast failed.', [
                    'task_id' => $task->id,
                    'board_id' => $task->board_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::created(function (Task $task) {
            try {
                TaskUpdated::dispatch($task);
            } catch (\Throwable $e) {
                Log::warning('Task create broadcast failed.', [
                    'task_id' => $task->id,
                    'board_id' => $task->board_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        static::deleted(function (Task $task) {
            try {
                broadcast(new TaskUpdated($task))->toOthers();
            } catch (\Throwable $e) {
                Log::warning('Task delete broadcast failed.', [
                    'task_id' => $task->id,
                    'board_id' => $task->board_id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
