<?php

use App\Models\Tenant;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

return [
    'tenant_finder' => null,

    'tenant_artisan_search_fields' => [
        'id',
    ],

    'switch_tenant_tasks' => [
    ],

    'tenant_model' => Tenant::class,

    'queues_are_tenant_aware_by_default' => true,

    // Critical: tenant-aware models must use this connection, not mysql.
    'tenant_database_connection_name' => 'tenant',
    'landlord_database_connection_name' => 'mysql',

    'current_tenant_context_key' => 'tenantId',
    'current_tenant_container_key' => 'currentTenant',

    'shared_routes_cache' => false,

    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    'tenant_aware_interface' => TenantAware::class,
    'not_tenant_aware_interface' => NotTenantAware::class,

    'tenant_aware_jobs' => [
    ],

    'not_tenant_aware_jobs' => [
    ],
];
