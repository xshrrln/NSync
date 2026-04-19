<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $isPlatformAdmin = strcasecmp((string) $user->email, 'admin@nsync.com') === 0
            || $user->hasRole('Platform Administrator');

        if (! $isPlatformAdmin) {
            abort(403, 'User does not have the right roles.');
        }

        return $next($request);
    }
}

