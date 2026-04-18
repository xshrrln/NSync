<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patch extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'sql_migrations'
    ];

    protected $casts = [
        'sql_migrations' => 'array'
    ];

    public function getNameAttribute(): string
    {
        return (string) $this->title;
    }
}

