<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'members_limit',
        'boards_limit',
        'storage_limit',
        'features',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
    ];

    protected static function booted()
    {
        static::saved(function ($plan) {
            Cache::forget('config.plans');
        });

        static::deleted(function ($plan) {
            Cache::forget('config.plans');
        });
    }

    public static function getCachedPlans()
    {
        return Cache::remember('config.plans', 3600, function () {
            return self::where('is_active', true)
                ->orderBy('members_limit')
                ->get()
                ->keyBy('slug')
                ->map(function ($plan) {
                    return [
                        'price' => $plan->price,
                        'members_limit' => $plan->members_limit,
                        'boards_limit' => $plan->boards_limit,
                        'storage_limit' => $plan->storage_limit,
                        'features' => $plan->features ?? [],
                    ];
                })
                ->toArray();
        });
    }
}

