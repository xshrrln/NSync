<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Task extends Model
{
    use UsesTenantConnection, LogsActivity;

    protected $fillable = ['tenant_id', 'title', 'description', 'assignees', 'labels', 'due_date', 'attachments', 'checklists', 'stage_id', 'board_id', 'user_id', 'position'];

    protected static function booted()
    {
        static::addGlobalScope('tenant', function ($builder) {
            $tenant = app()->has('currentTenant') ? app('currentTenant') : null;
            if ($tenant) {
                $builder->where('tenant_id', $tenant->id);
            }
        });
    }

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
}