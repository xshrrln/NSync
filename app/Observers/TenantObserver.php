<?php

namespace App\Observers;

use App\Models\Tenant;

class TenantObserver
{
    /**
     * When a tenant is created, assign a deterministic database name only.
     * Actual tenant DB provisioning is triggered explicitly on approval.
     */
    public function created(Tenant $tenant): void
    {
        $dbName = Tenant::generateDatabaseName($tenant->name, $tenant->id);

        if (blank($tenant->database) || $tenant->database !== $dbName) {
            $tenant->updateQuietly(['database' => $dbName]);
        }
    }
}
