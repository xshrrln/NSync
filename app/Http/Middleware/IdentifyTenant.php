<?php

namespace App\Http\Middleware;

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
        $host = strtolower($request->getHost());
        $isCentralHost = $this->isCentralHost($host);
        $isAuthRoute = $request->is('login*', 'register*', 'password*', 'forgot-password', 'auth/*', 'google*', 'admin*');

        // Keep central DB for auth/public/admin routes.
        if ($isAuthRoute) {
            if (! $isCentralHost) {
                $tenant = $this->resolveTenantByHost($host);

                if ($tenant) {
                    // Auth routes should keep the central DB connection, but still expose
                    // tenant theme data so guest pages render with workspace branding.
                    app()->instance('currentTenant', $tenant);
                    return $next($request);
                }
            }

            app()->instance('currentTenant', null);
            return $next($request);
        }

        $tenant = null;

        if ($isCentralHost) {
            if (auth()->check() && auth()->user()->tenant && ! $isAuthRoute) {
                $tenant = auth()->user()->tenant;
                $tenantHost = strtolower($tenant->domain);
                $uri = $request->getRequestUri();
                $scheme = $request->getScheme();
                $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;
                $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';

                return redirect()->to("{$scheme}://{$tenantHost}{$portSegment}{$uri}");
            }
        } else {
            // Tenant host: resolve strictly by host only (no user-tenant fallback).
            $tenant = $this->resolveTenantByHost($host);

            if (auth()->check()) {
                if ($this->cameFromCentralLogin($request)) {
                    auth()->guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    app()->instance('currentTenant', $tenant);

                    $loginUrl = $tenant
                        ? "http://{$host}" . $this->portSegment($request) . route('login', absolute: false)
                        : route('login', absolute: false);

                    return redirect()->to($loginUrl)->with('error', 'Please sign in to access this workspace.');
                }

                $userTenant = auth()->user()->tenant;

                if (! $tenant || ! $userTenant || $userTenant->id !== $tenant->id) {
                    auth()->guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    app()->instance('currentTenant', $tenant);

                    $loginUrl = $tenant
                        ? "http://{$host}" . $this->portSegment($request) . route('login', absolute: false)
                        : route('login', absolute: false);

                    return redirect()->to($loginUrl)->with('error', 'Please sign in to access this workspace.');
                }
            }
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

        // Do not auto-provision tenant DB here. Provisioning is explicit in approval flow.
        $dbExists = DB::selectOne(
            'SELECT SCHEMA_NAME FROM information_schema.schemata WHERE SCHEMA_NAME = ?',
            [$tenant->database]
        );

        if (!$dbExists) {
            Log::warning('Tenant database missing during request. Provisioning has not completed yet.', [
                'tenant_id' => $tenant->id,
                'tenant_domain' => $tenant->domain,
                'tenant_database' => $tenant->database,
            ]);

            // Do not interrupt central-host pages (pending approval, profile, admin, etc.).
            if ($isCentralHost) {
                app()->instance('currentTenant', null);
                return $next($request);
            }

            abort(503, 'Workspace is still being provisioned. Please try again in a moment.');
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

    private function resolveTenantByHost(string $host): ?Tenant
    {
        return Tenant::where('domain', $host)->first()
            ?? Tenant::where('domain', str_replace('www.', '', $host))->first();
    }

    private function portSegment(Request $request): string
    {
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;

        return $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
    }

    private function cameFromCentralLogin(Request $request): bool
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return false;
        }

        $refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $refererPath = (string) parse_url($referer, PHP_URL_PATH);

        return $refererHost === 'nsync.localhost' && str_starts_with($refererPath, '/login');
    }
}
