<?php

use Illuminate\Foundation\Application;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        $schedule->command('tasks:send-due-reminders')->dailyAt('08:00');
    })
->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectTo(
            guests: fn () => route('login', absolute: false),
            users: function (Request $request): string {
                $user = Auth::user();

                if (! $user) {
                    return route('landing', absolute: false);
                }

                if ($user->email === 'admin@nsync.com' || $user->hasRole('Platform Administrator')) {
                    $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;
                    $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';

                    return "http://nsync.localhost{$portSegment}/dashboard";
                }

                if ($user->tenant) {
                    $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;
                    $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
                    $tenantHost = str_contains((string) $user->tenant->domain, '.')
                        ? $user->tenant->domain
                        : "{$user->tenant->domain}.localhost";

                    return "http://{$tenantHost}{$portSegment}/dashboard";
                }

                return route('pending-approval', absolute: false);
            }
        );

        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
            'approved' => \App\Http\Middleware\EnsureTenantApproved::class,
            'platform_admin' => \App\Http\Middleware\EnsurePlatformAdmin::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        // Allow login validation to run (and show user-friendly tenant/org messages)
        // instead of returning a raw 419 in cross-host session edge cases.
        $middleware->validateCsrfTokens(except: [
            'login',
        ]);
        
        $middleware->web(append: [
            \App\Http\Middleware\IdentifyTenant::class,
            \App\Http\Middleware\AdminAuditRequestLogger::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->is('login')) {
                return redirect()
                    ->to(route('login', absolute: false))
                    ->withErrors([
                        'email' => 'User does not exist. Make sure you belong in this organization.',
                    ])
                    ->withInput($request->except('password'));
            }
        });
    })->create();
