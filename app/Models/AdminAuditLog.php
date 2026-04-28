<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'context' => 'array',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Admin audit logs are immutable.');
        });

        static::deleting(function (): void {
            throw new LogicException('Admin audit logs are immutable.');
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withoutGlobalScopes();
    }
}
