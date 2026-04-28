<?php

namespace App\Models;

use App\Casts\LenientEncrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;

class Message extends Model
{
    use HasFactory, UsesTenantConnection;

    protected $connection = 'tenant';

    protected $fillable = [
        'room_id',
        'sender_id',
        'message',
    ];

    protected $casts = [
        'message' => LenientEncrypted::class,
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function user()
    {
        return $this->sender();
    }

    // Scope for room
    public function scopeForRoom($query, $roomId)
    {
        return $query->where('room_id', $roomId);
    }
}
