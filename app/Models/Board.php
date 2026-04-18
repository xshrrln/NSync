<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Board extends Model
{
    use UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = ['tenant_id', 'name', 'slug', 'starred_by', 'members'];

    protected $casts = [
        'name' => 'encrypted',
        'starred_by' => 'array',
        'members' => 'encrypted:array',
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

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the stages (columns) for the board.
     */
    public function stages(): HasMany
    {
        return $this->hasMany(Stage::class)->orderBy('position');
    }

    /**
     * Get all tasks associated with this board.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
