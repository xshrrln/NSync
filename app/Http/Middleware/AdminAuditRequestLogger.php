<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\AdminAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AdminAuditRequestLogger
{
    public function __construct(
        private readonly AdminAuditLogger $auditLogger
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $beforeUser = $request->user();
        $response = $next($request);
        $afterUser = $request->user();
        $actor = $beforeUser instanceof User ? $beforeUser : ($afterUser instanceof User ? $afterUser : null);

        if (! $this->shouldLog($request, $beforeUser, $afterUser, $actor)) {
            return $response;
        }

        $event = $this->resolveEvent($request, $response, $beforeUser, $afterUser);
        if ($event === null) {
            return $response;
        }

        $this->auditLogger->log(
            user: $actor,
            action: $event['action'],
            description: $event['description'] ?? null,
            request: $request,
            context: $event['context'] ?? [],
            statusCode: $response->getStatusCode(),
            subjectType: $event['subject_type'] ?? null,
            subjectId: $event['subject_id'] ?? null,
        );

        return $response;
    }

    private function shouldLog(Request $request, mixed $beforeUser, mixed $afterUser, ?User $actor): bool
    {
        if ($request->isMethod('OPTIONS') || $request->is('up')) {
            return false;
        }

        if ($request->attributes->get('audit_log_handled') === true) {
            return false;
        }

        if ($request->routeIs('login') && $afterUser instanceof User && ($this->isPlatformAdmin($afterUser) || $this->isTenantUser($afterUser))) {
            return true;
        }

        if ($request->routeIs('logout') && $beforeUser instanceof User && ($this->isPlatformAdmin($beforeUser) || $this->isTenantUser($beforeUser))) {
            return true;
        }

        if (! $actor || ! $this->isPlatformAdmin($actor)) {
            return false;
        }

        return $this->isCentralAdminRequest($request);
    }

    private function resolveEvent(Request $request, Response $response, mixed $beforeUser, mixed $afterUser): ?array
    {
        if ($request->routeIs('login') && ! $beforeUser && $afterUser instanceof User && $this->isPlatformAdmin($afterUser)) {
            return [
                'action' => 'Admin login',
                'description' => 'Signed in to the central admin app.',
                'context' => [
                    'destination' => 'central-admin',
                    'audience' => 'admin',
                ],
            ];
        }

        if ($request->routeIs('login') && ! $beforeUser && $afterUser instanceof User && $this->isTenantUser($afterUser)) {
            return [
                'action' => 'Tenant login',
                'description' => 'Signed in to the tenant workspace.',
                'context' => [
                    'destination' => 'tenant-workspace',
                    'audience' => 'tenant',
                    'tenant_id' => $afterUser->tenant?->id,
                    'tenant_name' => $afterUser->tenant?->name,
                    'tenant_domain' => $afterUser->tenant?->domain,
                ],
                'subject_type' => $afterUser->tenant ? 'Tenant' : null,
                'subject_id' => $afterUser->tenant?->id,
            ];
        }

        if ($request->routeIs('logout') && $beforeUser instanceof User && $this->isPlatformAdmin($beforeUser)) {
            return [
                'action' => 'Admin logout',
                'description' => 'Signed out of the central admin app.',
                'context' => [
                    'audience' => 'admin',
                ],
            ];
        }

        if ($request->routeIs('logout') && $beforeUser instanceof User && $this->isTenantUser($beforeUser)) {
            return [
                'action' => 'Tenant logout',
                'description' => 'Signed out of the tenant workspace.',
                'context' => [
                    'audience' => 'tenant',
                    'tenant_id' => $beforeUser->tenant?->id,
                    'tenant_name' => $beforeUser->tenant?->name,
                    'tenant_domain' => $beforeUser->tenant?->domain,
                ],
                'subject_type' => $beforeUser->tenant ? 'Tenant' : null,
                'subject_id' => $beforeUser->tenant?->id,
            ];
        }

        if ($this->isLivewireRequest($request)) {
            return $this->resolveLivewireEvent($request, $response);
        }

        $method = strtoupper($request->method());
        $routeName = (string) ($request->route()?->getName() ?? '');
        $routeLabel = $this->routeLabel($routeName, $request->path());
        $subject = $this->resolveSubject($request);

        return [
            'action' => $method === 'GET' || $method === 'HEAD'
                ? "Viewed {$routeLabel}"
                : $this->mutationLabel($routeName, $method, $routeLabel),
            'description' => $method === 'GET' || $method === 'HEAD'
                ? "Opened {$routeLabel}."
                : "Completed {$method} request for {$routeLabel}.",
            'context' => [
                'route_name' => $routeName,
                'response_status' => $response->getStatusCode(),
                'route_parameters' => $this->routeParameters($request),
                'audience' => 'admin',
            ],
            'subject_type' => $subject['type'] ?? null,
            'subject_id' => $subject['id'] ?? null,
        ];
    }

    private function resolveLivewireEvent(Request $request, Response $response): ?array
    {
        $componentPayload = collect($request->input('components', []));
        $components = $componentPayload
            ->map(fn ($component) => (string) data_get($component, 'snapshot.memo.name', data_get($component, 'snapshot.memo.path', 'livewire-component')))
            ->filter()
            ->values()
            ->all();

        $methods = $componentPayload
            ->flatMap(fn ($component) => collect(data_get($component, 'calls', []))->pluck('method'))
            ->filter()
            ->map(fn ($method) => (string) $method)
            ->values()
            ->all();

        $referer = (string) $request->headers->get('referer', '');
        $summary = $methods !== []
            ? 'Called ' . implode(', ', $methods)
            : 'Processed Livewire interaction';

        return [
            'action' => 'Admin Livewire interaction',
            'description' => $summary . '.',
            'context' => [
                'components' => $components,
                'methods' => $methods,
                'response_status' => $response->getStatusCode(),
                'referer' => $referer,
            ],
        ];
    }

    private function mutationLabel(string $routeName, string $method, string $routeLabel): string
    {
        return match ($routeName) {
            'admin.tenants.approve' => 'Approved tenant workspace',
            'admin.tenants.reject' => 'Rejected tenant workspace',
            'admin.tenants.suspend' => 'Suspended tenant workspace',
            'admin.tenants.resume' => 'Resumed tenant workspace',
            'admin.tenants.update' => 'Updated tenant workspace',
            'admin.tenants.upgrade-plan' => 'Changed tenant plan',
            'admin.settings.update' => 'Updated central settings',
            default => "{$method} {$routeLabel}",
        };
    }

    private function routeLabel(string $routeName, string $path): string
    {
        if ($routeName !== '') {
            $label = Str::of($routeName)
                ->replace('.', ' ')
                ->replace('-', ' ')
                ->replace('_', ' ')
                ->replace('admin ', '')
                ->headline()
                ->toString();

            return $label !== '' ? $label : 'Admin page';
        }

        return Str::headline(str_replace('/', ' ', $path));
    }

    private function routeParameters(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(function ($value) {
                if (is_object($value) && method_exists($value, 'getKey')) {
                    return [
                        'type' => class_basename($value),
                        'id' => $value->getKey(),
                    ];
                }

                return is_scalar($value) ? $value : null;
            })
            ->filter()
            ->all();
    }

    private function resolveSubject(Request $request): ?array
    {
        foreach ($request->route()?->parameters() ?? [] as $value) {
            if (is_object($value) && method_exists($value, 'getKey')) {
                return [
                    'type' => class_basename($value),
                    'id' => $value->getKey(),
                ];
            }
        }

        return null;
    }

    private function isCentralAdminRequest(Request $request): bool
    {
        $host = Str::lower($request->getHost());
        if ($host === 'nsync.localhost') {
            return true;
        }

        if ($request->routeIs('admin.*') || $request->routeIs('admin.dashboard')) {
            return true;
        }

        if ($this->isLivewireRequest($request)) {
            $referer = Str::lower((string) $request->headers->get('referer', ''));

            return str_contains($referer, 'nsync.localhost/admin')
                || str_contains($referer, 'nsync.localhost/dashboard');
        }

        return false;
    }

    private function isLivewireRequest(Request $request): bool
    {
        return $request->is('livewire/*')
            || $request->headers->has('X-Livewire');
    }

    private function isPlatformAdmin(mixed $user): bool
    {
        return $user instanceof User
            && (strcasecmp((string) $user->email, 'admin@nsync.com') === 0 || $user->hasRole('Platform Administrator'));
    }

    private function isTenantUser(mixed $user): bool
    {
        return $user instanceof User
            && ! $this->isPlatformAdmin($user)
            && $user->tenant !== null;
    }
}
