<?php

namespace App\Models;

use App\Casts\LenientEncrypted;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Stage extends Model
{
    use UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = ['tenant_id', 'board_id', 'name', 'position'];

    protected $casts = [
        'name' => LenientEncrypted::class,
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

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('position');
    }
}
