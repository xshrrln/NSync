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
            $basePlans = (array) config('plans', []);

            return self::where('is_active', true)
                ->orderBy('members_limit')
                ->get()
                ->keyBy('slug')
                ->map(function ($plan) use ($basePlans) {
                    $fallback = (array) ($basePlans[$plan->slug] ?? []);
                    $mergedFeatures = array_values(array_unique(array_merge(
                        (array) ($fallback['features'] ?? []),
                        (array) ($plan->features ?? []),
                    )));

                    return [
                        'price' => $plan->price ?: ($fallback['price'] ?? ''),
                        'members_limit' => (int) ($plan->members_limit ?? $fallback['members_limit'] ?? 0),
                        'boards_limit' => (int) ($plan->boards_limit ?? $fallback['boards_limit'] ?? 0),
                        'storage_limit' => (int) ($plan->storage_limit ?? $fallback['storage_limit'] ?? 0),
                        'features' => $mergedFeatures,
                    ];
                })
                ->toArray();
        });
    }
}
