<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Illuminate\Support\Facades\Schema;
use App\Models\Tenant;
use App\Models\Plan;
use App\Observers\TenantObserver;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        try {
            if (Schema::hasTable('plans')) {
                $databasePlans = Plan::getCachedPlans();
                if (!empty($databasePlans)) {
                    config(['plans' => $databasePlans]);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        // Your existing Policies
        Gate::policy(\App\Models\Tenant::class, \App\Policies\TenantPolicy::class);
        Gate::policy(\App\Models\Board::class, \App\Policies\BoardPolicy::class);
        Gate::policy(\App\Models\Task::class, \App\Policies\TaskPolicy::class);

        // FORCE LIVEWIRE TO WORK ACROSS SUBDOMAINS
        // This fixes the "Add Member" button and the Globe icon issues
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/livewire/livewire.js', $handle);
        });

        Livewire::setUpdateRoute(function ($handle) {
            return Route::post('/livewire/update', $handle);
        });

        // Auto-provision tenant databases on creation
        Tenant::observe(TenantObserver::class);

        // (legacy) central app settings bootstrap moved to migration with guards
    }
}
