<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class ActivityLog extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = [
        'user_id',
        'task_id', 
        'old_stage_id',
        'new_stage_id',
        'ip_address'
    ];

    protected $casts = [
        'old_stage_id' => 'integer',
        'new_stage_id' => 'integer',
    ];

    protected static function booted()
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
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function oldStage()
    {
        return $this->belongsTo(Stage::class, 'old_stage_id');
    }

    public function newStage()
    {
        return $this->belongsTo(Stage::class, 'new_stage_id');
    }
}

