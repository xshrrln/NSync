<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PendingInvite extends Model
{
    use HasFactory;

    protected $connection = 'mysql';

    protected $fillable = [
        'email',
        'token',
        'role',
        'tenant_id',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invite) {
            if (empty($invite->token)) {
                $invite->token = Str::random(64);
            }
        });
    }

    public function isValid()
    {
        return !$this->hasExpired();
    }

    public function hasExpired()
    {
        return $this->created_at->lt(now()->subDays(7));
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function scopePendingForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}

