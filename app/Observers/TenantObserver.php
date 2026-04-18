<?php

namespace App\Observers;

use App\Jobs\CreateTenantDatabase;
use App\Models\Tenant;
use Illuminate\Support\Str;

class TenantObserver
{
    /**
     * When a tenant is created, generate its database name, create the DB,
     * and run tenant migrations.
     */
    public function created(Tenant $tenant): void
    {
        // Build database name like "{name}_nsync_db" using a safe slug
        $slug = Str::slug($tenant->name ?? 'tenant', '_');
        $dbName = ($slug ?: 'tenant_'.$tenant->id) . '_nsync_db';

        if (blank($tenant->database) || $tenant->database !== $dbName) {
            $tenant->updateQuietly(['database' => $dbName]);
        }

        // Kick off DB creation + migrations asynchronously
        dispatch(new CreateTenantDatabase($tenant->fresh()));
    }
}
