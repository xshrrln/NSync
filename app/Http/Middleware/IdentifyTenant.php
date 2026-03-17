<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = null;

        // Try subdomain or custom domain
        $host = $request->getHost();
        $subdomain = explode('.', $host)[0];
        if ($subdomain !== 'www' && $subdomain !== 'localhost') {
            $tenant = Tenant::where('domain', $subdomain)->first();
        }

        // Fallback to user tenant
        if (!$tenant && auth()->check()) {
            $tenant = auth()->user()->tenant;
        }

        if ($tenant) {
            app()->instance('currentTenant', $tenant);
            $tenant->makeCurrent();
        }

        return $next($request);
    }
}
