<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Models\Tenant;
use App\Models\User;

class EnsureTenantApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        $host = strtolower($request->getHost());

        $isPlatformAdmin = $user->email === 'admin@nsync.com' || $user->hasRole('Platform Administrator');
        if ($isPlatformAdmin) {
            // Central app only for platform admins.
            if ($host === 'nsync.localhost') {
                return $next($request);
            }

            return redirect()->route('admin.dashboard')
                ->with('error', 'Global admins access central dashboard only.');
        }

        $host = strtolower($request->getHost());
        if (in_array($host, ['127.0.0.1', 'localhost', 'nsync.localhost'])) {
            return $next($request);
        }

        $user = auth()->user();

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort();
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        if ($request->getHost() === 'nsync.localhost' && !$user->hasRole('Platform Administrator') && $user->tenant) {
            return redirect()->to("http://{$user->tenant->domain}{$portSegment}/dashboard");
        }
        
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        // If currentTenant is missing or null, fallback to authenticated user's tenant.
        if (!$tenant) {
            if ($user->tenant) {
                $tenant = $user->tenant;
                app()->instance('currentTenant', $tenant);
                Log::info('Set currentTenant from user for central access', ['tenant_id' => $tenant->id]);
            } else {
                Auth::logout();
                return redirect()->route('login')
                    ->with('error', 'No organization is linked to this account yet.');
            }
        }

        // Ensure user belongs to current tenant
        if ($tenant && ! $user->belongsToTenant($tenant)) {
            Auth::logout();
            return redirect()->route('login')
                ->with('error', 'Access denied. Invalid user for this workspace.');
        }

        if ($tenant && ! $tenant->is_active) {
            $message = match($tenant->status) {
                'pending' => 'Your organization is waiting for admin approval.',
                'disabled' => 'Your organization has been disabled. Contact support.',
                default => 'Your organization is not active.'
            };
            Auth::logout();
            return redirect()->route('login')
                ->with('error', $message);
        }

        if ($tenant && $tenant->isTwoFactorEnforcedForUser($user)) {
            $isTwoFactorRoute = $request->routeIs('two-factor.*');
            $isVerified = $this->isTwoFactorVerified($request, $tenant, $user);

            if (! $isVerified && ! $isTwoFactorRoute) {
                $this->queueTwoFactorCode($request, $tenant, $user);
                $request->session()->put('url.intended', $request->fullUrl());

                return redirect()->route('two-factor.challenge.show');
            }
        }

        if ($tenant && $tenant->due_date) {
            $dueDate = Carbon::parse($tenant->due_date)->startOfDay();
            $today = now()->startOfDay();
            $daysUntilDue = $today->diffInDays($dueDate, false); // negative when overdue

            if ($daysUntilDue <= 3) {
                $noticeKey = "subscription_due_notice_{$tenant->id}_" . $today->toDateString();

                if (!session()->has($noticeKey)) {
                    if ($daysUntilDue < -3) {
                        $message = 'Subscription notice: your workspace subscription is overdue. Please renew as soon as possible.';
                    } elseif ($daysUntilDue < 0) {
                        $overdueDays = abs($daysUntilDue);
                        $suffix = $overdueDays === 1 ? 'day' : 'days';
                        $message = "Subscription notice: your workspace subscription is overdue by {$overdueDays} {$suffix}.";
                    } elseif ($daysUntilDue === 0) {
                        $message = 'Subscription reminder: your workspace subscription is due today.';
                    } elseif ($daysUntilDue === 1) {
                        $message = 'Subscription reminder: your workspace subscription is due tomorrow.';
                    } else {
                        $message = "Subscription reminder: your workspace subscription is due in {$daysUntilDue} days.";
                    }

                    if ($message) {
                        session()->flash('warning', $message);
                        session()->put($noticeKey, true);
                    }
                }
            }
        }

        return $next($request);
    }

    private function isTwoFactorVerified(Request $request, Tenant $tenant, User $user): bool
    {
        if ($this->hasTrustedDeviceCookie($request, $tenant, $user)) {
            return true;
        }

        $key = $this->verifiedSessionKey($tenant, $user);
        $state = $request->session()->get($key);
        $window = $tenant->twoFactorVerificationWindowSeconds();

        if (is_bool($state)) {
            return $window === null ? $state : false;
        }

        if (! is_array($state) || ! isset($state['verified_at'])) {
            return false;
        }

        $verifiedAt = (int) $state['verified_at'];
        if ($verifiedAt <= 0) {
            return false;
        }

        if ($window === null) {
            return true;
        }

        $valid = (time() - $verifiedAt) <= $window;
        if (! $valid) {
            $request->session()->forget($key);
        }

        return $valid;
    }

    private function queueTwoFactorCode(Request $request, Tenant $tenant, User $user): void
    {
        $sessionCode = (string) $request->session()->get('two_factor.code', '');
        $expiresAt = (int) $request->session()->get('two_factor.expires_at', 0);
        $sentAt = (int) $request->session()->get('two_factor.sent_at', 0);

        if ($sessionCode !== '' && time() < $expiresAt && (time() - $sentAt) < 60) {
            return;
        }

        $ttlMinutes = $tenant->twoFactorCodeTtlMinutes();
        $code = (string) random_int(100000, 999999);

        $request->session()->put('two_factor.code', $code);
        $request->session()->put('two_factor.expires_at', time() + ($ttlMinutes * 60));
        $request->session()->put('two_factor.sent_at', time());

        try {
            Mail::raw(
                "Your NSync verification code is {$code}. It expires in {$ttlMinutes} minutes.",
                fn ($message) => $message->to($user->email)->subject('Your NSync verification code')
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to send two-factor code email.', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function verifiedSessionKey(Tenant $tenant, User $user): string
    {
        return "two_factor.verified.tenant_{$tenant->id}.user_{$user->id}";
    }

    private function hasTrustedDeviceCookie(Request $request, Tenant $tenant, User $user): bool
    {
        $settings = $tenant->twoFactorSettings();
        if (($settings['frequency'] ?? 'new_device') !== 'new_device') {
            return false;
        }

        $cookieName = $this->trustedDeviceCookieName($tenant, $user);
        $cookieValue = (string) $request->cookie($cookieName, '');
        if ($cookieValue === '') {
            return false;
        }

        return hash_equals($this->trustedDeviceToken($request, $tenant, $user), $cookieValue);
    }

    private function trustedDeviceCookieName(Tenant $tenant, User $user): string
    {
        return "nsync_2fa_trusted_{$tenant->id}_{$user->id}";
    }

    private function trustedDeviceToken(Request $request, Tenant $tenant, User $user): string
    {
        $agent = Str::limit((string) $request->userAgent(), 500, '');

        return hash_hmac('sha256', "{$tenant->id}|{$user->id}|{$agent}", (string) config('app.key'));
    }
}
