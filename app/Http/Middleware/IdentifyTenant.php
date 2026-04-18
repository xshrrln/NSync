<?php

namespace App\Http\Middleware;

use App\Jobs\CreateTenantDatabase;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // Keep central DB for auth/public/admin routes.
        if ($request->is('login*', 'register*', 'password*', 'forgot-password', 'auth/*', 'google*', 'admin*')) {
            app()->instance('currentTenant', null);
            return $next($request);
        }

        $host = strtolower($request->getHost());
        $tenant = null;

        if ($this->isCentralHost($host)) {
            // Central hosts can resolve tenant from authenticated user for direct dashboard use.
            if (auth()->check() && auth()->user()->tenant) {
                $tenant = auth()->user()->tenant;
            }
        } else {
            // Tenant host: resolve strictly by host only (no user-tenant fallback).
            $tenant = Tenant::where('domain', $host)->first()
                ?? Tenant::where('domain', str_replace('www.', '', $host))->first();
        }

        Log::info('Tenant identification attempt', [
            'host' => $host,
            'tenant_id' => $tenant?->id,
            'tenant_domain' => $tenant?->domain,
            'user_id' => auth()->id(),
        ]);

        if (!$tenant) {
            app()->instance('currentTenant', null);
            return $next($request);
        }

        // Ensure tenant database exists; create on demand if missing.
        $dbExists = DB::selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME = ?',
            [$tenant->database]
        );

        if (!$dbExists) {
            (new CreateTenantDatabase($tenant))->handle();
        }

        // Activate tenant connection for tenant-aware models.
        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        app()->instance('currentTenant', $tenant);
        $tenant->makeCurrent();

        return $next($request);
    }

    private function isCentralHost(string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost', 'nsync.localhost'], true);
    }
}
