<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Board extends Model
{
protected $fillable = ['tenant_id', 'name', 'slug', 'starred_by', 'members'];

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