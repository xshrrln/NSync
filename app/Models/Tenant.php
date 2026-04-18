<?php

namespace App\Models;

use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Models\Board;
use App\Models\User;

class Tenant extends BaseTenant
{
    protected $fillable = [
        'organization', 'name', 'address', 'tenant_admin', 'tenant_admin_email',
        'domain', 'database', 'plan', 'start_date', 'due_date', 
        'status', 'theme', 'actions', 'billing_data', 'patches_applied'
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'patches_applied' => 'array',
        'actions' => 'array',
        'billing_data' => 'array',
    ];

    public function planConfig(): array
    {
        $plans = config('plans', []);
        $currentPlan = strtolower($this->plan ?? 'free');

        return $plans[$currentPlan] ?? ($plans['free'] ?? []);
    }

    public function planFeatures(): array
    {
        return array_values(array_unique($this->planConfig()['features'] ?? []));
    }

    public function featureCatalog(): array
    {
        return collect(config('features.categories'))
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Check if the tenant has reached their plan limits.
     */
    public function hasReachedLimit(string $feature): bool
    {
        $planConfig = $this->planConfig();

        $limit = match ($feature) {
            'members' => (int) ($planConfig['members_limit'] ?? 0),
            'boards' => (int) ($planConfig['boards_limit'] ?? 0),
            'storage' => (int) ($planConfig['storage_limit'] ?? 0),
            default => 0,
        };

        if ($limit <= 0) {
            return false;
        }

        // Special "effectively unlimited" cutoffs used by UI.
        if ($feature === 'boards' && $limit >= 999) {
            return false;
        }
        if ($feature === 'members' && $limit >= 999) {
            return false;
        }
        if ($feature === 'storage' && $limit >= 999999) {
            return false;
        }

        $currentCount = match ($feature) {
            'members' => User::where('tenant_id', $this->id)->count(),
            'boards' => Board::where('tenant_id', $this->id)->count(),
            'storage' => (float) $this->storage_used,
            default => 0,
        };

        return $currentCount >= $limit;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getStorageUsedAttribute()
    {
        $totalBytes = 0;

        // 1) Database size (boards/tasks/stages + all tenant tables).
        if (!empty($this->database)) {
            try {
                $row = DB::connection('mysql')->selectOne(
                    'SELECT COALESCE(SUM(data_length + index_length), 0) AS total FROM information_schema.tables WHERE table_schema = ?',
                    [$this->database]
                );
                $totalBytes += (int) ($row->total ?? 0);
            } catch (\Throwable) {
                // keep rendering even if metadata query fails
            }
        }

        // 2) File storage usage (attachments/uploads).
        $tenantPath = storage_path("app/tenants/{$this->database}");
        if (is_dir($tenantPath)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tenantPath));
            foreach ($files as $file) {
                if ($file->isFile()) {
                    $totalBytes += $file->getSize();
                }
            }
        }

        // Return MB so existing admin UI keeps working.
        return $totalBytes / 1024 / 1024;
    }

    public function getMemberCountAttribute()
    {
        return $this->users()->count();
    }

    public function getThemeAttribute($value)
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?: [
                'primary' => '#16A34A',
                'secondary' => '#FFFFFF'
            ];
        }

        return [
            'primary' => '#16A34A',
            'secondary' => '#FFFFFF'
        ];
    }

    /**
     * Get enabled features from actions JSON, validated against catalog.
     */
    public function featuresFromActions(): array
    {
        $catalogFeatures = $this->featureCatalog();
        $actions = is_array($this->actions)
            ? $this->actions
            : ($this->actions ? json_decode((string) $this->actions, true) ?: [] : []);

        return array_values(array_intersect($catalogFeatures, array_keys(array_filter($actions))));
    }

    /**
     * Check if a feature is enabled for the tenant (plan default or toggled on).
     */
    public function hasFeature(string $feature): bool
    {
        $catalog = $this->featureCatalog();
        if (! in_array($feature, $catalog, true)) {
            return false;
        }

        $planFeatures = $this->planFeatures();
        $actions = is_array($this->actions)
            ? $this->actions
            : ($this->actions ? json_decode((string) $this->actions, true) ?: [] : []);

        if (array_key_exists($feature, $actions)) {
            return (bool) $actions[$feature];
        }

        return in_array($feature, $planFeatures, true);
    }

    public function enabledFeatures(): array
    {
        return collect($this->featureCatalog())
            ->filter(fn (string $feature) => $this->hasFeature($feature))
            ->values()
            ->all();
    }

    public function twoFactorSettings(): array
    {
        $defaults = [
            'enabled' => false,
            'scope' => 'all_members',
            'frequency' => 'new_device',
            'code_ttl_minutes' => 10,
            'delivery' => 'email',
        ];

        $actions = is_array($this->actions)
            ? $this->actions
            : ($this->actions ? json_decode((string) $this->actions, true) ?: [] : []);

        $raw = $actions['two_factor_settings'] ?? [];
        if (! is_array($raw)) {
            $raw = [];
        }

        $scope = in_array(($raw['scope'] ?? ''), ['all_members', 'supervisors_only'], true)
            ? $raw['scope']
            : $defaults['scope'];

        $frequency = in_array(($raw['frequency'] ?? ''), ['new_device', 'once_per_session', 'once_per_day', 'once_per_week'], true)
            ? $raw['frequency']
            : $defaults['frequency'];

        $ttl = (int) ($raw['code_ttl_minutes'] ?? $defaults['code_ttl_minutes']);
        $ttl = max(5, min(30, $ttl));

        return [
            'enabled' => (bool) ($raw['enabled'] ?? $defaults['enabled']),
            'scope' => $scope,
            'frequency' => $frequency,
            'code_ttl_minutes' => $ttl,
            'delivery' => 'email',
        ];
    }

    public function isTwoFactorEnforcedForUser(?User $user): bool
    {
        if (! $this->hasFeature('two-factor')) {
            return false;
        }

        $settings = $this->twoFactorSettings();
        if (! $settings['enabled']) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if ($settings['scope'] === 'supervisors_only') {
            return $user->hasRole('Team Supervisor');
        }

        return true;
    }

    public function twoFactorVerificationWindowSeconds(): ?int
    {
        return match ($this->twoFactorSettings()['frequency']) {
            'once_per_day' => 60 * 60 * 24,
            'once_per_week' => 60 * 60 * 24 * 7,
            default => null, // once_per_session
        };
    }

    public function twoFactorCodeTtlMinutes(): int
    {
        return (int) $this->twoFactorSettings()['code_ttl_minutes'];
    }

    /**
     * Scope for active tenants only
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['active']);
    }

    /**
     * Check if tenant is active
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['active']);
    }

    public function isSubscriptionExpired(): bool
    {
        if (blank($this->due_date)) {
            return false;
        }

        return Carbon::parse($this->due_date)->endOfDay()->isPast();
    }

    public function requiresSubscriptionRenewal(): bool
    {
        return $this->is_active && $this->isSubscriptionExpired();
    }

    public function subscriptionLockMessage(): string
    {
        return 'Your free trial has ended. Please avail a subscription to access your workspace data and continue using NSync.';
    }
}
