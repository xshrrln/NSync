<?php

namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant
{
    protected $fillable = ['name', 'domain', 'database', 'plan'];

    /**
     * Check if the tenant has reached their plan limits.
     */
    public function hasReachedLimit(string $feature): bool
    {
        // Source of truth for limits
        $limits = [
            'free'     => ['members' => 7,  'boards' => 5],
            'standard' => ['members' => 20, 'boards' => 999],
            'pro'      => ['members' => 999, 'boards' => 999],
        ];

        $currentPlan = $this->plan ?? 'free';
        $limit = $limits[$currentPlan][$feature] ?? 0;

        $currentCount = match ($feature) {
            'members' => User::where('tenant_id', $this->id)->count(),
            'boards'  => Stage::where('tenant_id', $this->id)->count(),
            default   => 0,
        };

        return $currentCount >= $limit;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
