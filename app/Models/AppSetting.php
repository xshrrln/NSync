<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AppSetting extends Model
{
    protected $fillable = ['data'];

    protected $casts = [
        'data' => 'array',
    ];

    /**
     * Get all settings as an array (ensures a single row exists).
     */
    public static function data(): array
    {
        if (!Schema::hasTable('app_settings')) {
            return static::defaults();
        }

        $settings = static::first();

        if (!$settings) {
            $settings = static::create(['data' => []]);
        }

        return $settings->data ?? static::defaults();
    }

    /**
     * Get a single setting by key.
     */
    public static function get(string $key, $default = null)
    {
        return static::data()[$key] ?? $default;
    }

    /**
     * Merge and persist new settings.
     */
    public static function updateSettings(array $payload): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        $settings = static::first() ?? static::create(['data' => []]);
        $settings->data = array_merge($settings->data ?? [], $payload);
        $settings->save();
    }

    /**
     * Fallback defaults when table is unavailable.
     */
    protected static function defaults(): array
    {
        return [
            'default_plan' => 'free',
            'notify_new_tenant' => false,
            'maintenance_enabled' => false,
            'maintenance_message' => 'All systems operational.',
            'support_email' => null,
        ];
    }
}
