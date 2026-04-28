<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use App\Models\User;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        $host = strtolower(request()->getHost());
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1'], true);
        $isCentralAdminHost = $host === 'nsync.localhost';

        if (($isLocalhost || $isCentralAdminHost) && Auth::check()) {
            $user = Auth::user();
            $isPlatformAdmin = $user && ($user->email === 'admin@nsync.com' || $user->hasRole('Platform Administrator'));

            if ($isCentralAdminHost && $isPlatformAdmin) {
                return redirect()->to($this->loginDestination($user));
            }

            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        if (Auth::check()) {
            return redirect()->to($this->loginDestination(Auth::user()));
        }

        return view('auth.login', [
            'logoutHostUrl' => $isCentralAdminHost ? $this->crossHostLogoutUrl(request()) : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();
        $destination = $this->loginDestination($user);

        // If host changes after login, perform one-time handoff so user won't see a second login page.
        if ($this->hostForUrl($destination) !== strtolower($request->getHost())) {
            return redirect()->to($this->createHandoffUrl($user, $destination));
        }

        return redirect()->to($destination);
    }

    /**
     * Complete one-time login handoff on the destination host.
     */
    public function handoff(Request $request): RedirectResponse
    {
        $token = $request->query('token');
        if (!$token || !is_string($token)) {
            return redirect()->to(route('login', absolute: false))->withErrors([
                'email' => 'Login session expired. Please sign in again.',
            ]);
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return redirect()->to(route('login', absolute: false))->withErrors([
                'email' => 'Login session expired. Please sign in again.',
            ]);
        }

        $expiresAt = (int) ($payload['exp'] ?? 0);
        if ($expiresAt <= now()->timestamp) {
            return redirect()->to(route('login', absolute: false))->withErrors([
                'email' => 'Login session expired. Please sign in again.',
            ]);
        }

        $expectedHost = strtolower($payload['host'] ?? '');
        $actualHost = strtolower($request->getHost());
        if (!$expectedHost || $expectedHost !== $actualHost) {
            return redirect()->to(route('login', absolute: false))->withErrors([
                'email' => 'Invalid organization login URL.',
            ]);
        }

        $userId = $payload['user_id'] ?? null;
        if (!$userId) {
            return redirect()->to(route('login', absolute: false))->withErrors([
                'email' => 'Login session is invalid. Please sign in again.',
            ]);
        }

        Auth::loginUsingId($userId, true);
        $request->session()->regenerate();

        $path = $payload['path'] ?? '/dashboard';
        return redirect()->to($path);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        // Redirect to the central app URL so tenant logout returns to localhost
        return redirect()->to(rtrim(config('app.url'), '/'));
    }

    public function hostLogout(Request $request)
    {
        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Build a tenant URL that respects the current app port (for localhost usage).
     */
    private function tenantUrl(?string $domain): string
    {
        if (!$domain) {
            return route('dashboard');
        }

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: request()->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        $host = str_contains($domain, '.') ? $domain : "{$domain}.localhost";

        return "http://{$host}{$portSegment}/dashboard";
    }

    /**
     * Get central admin URL.
     */
    /**
     * Get central admin URL.
     */
    private function adminUrl(): string
    {
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: request()->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        return "http://nsync.localhost{$portSegment}/dashboard";
    }

    /**
     * Determine a single post-login destination URL (no double login).
     */
    private function loginDestination(User $user): string
    {
        // Explicit admin check (bypass role cache issue)
        if ($user->email === 'admin@nsync.com' || $user->hasRole('Platform Administrator')) {
            return $this->adminUrl();
        }

        $tenant = $user->tenant;
        if ($tenant) {
            return $this->tenantUrl($tenant->domain);
        }

        return route('landing', absolute: false);
    }

    private function createHandoffUrl(User $user, string $destination): string
    {
        $host = $this->hostForUrl($destination);
        $path = parse_url($destination, PHP_URL_PATH) ?: '/dashboard';
        $query = parse_url($destination, PHP_URL_QUERY);
        if ($query) {
            $path .= '?' . $query;
        }

        $token = Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'host' => $host,
            'path' => $path,
            'exp' => now()->addMinutes(2)->timestamp,
        ], JSON_THROW_ON_ERROR));

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: request()->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        $queryString = http_build_query(['token' => $token]);

        return "http://{$host}{$portSegment}/auth/handoff?{$queryString}";
    }

    private function hostForUrl(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    private function crossHostLogoutUrl(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return null;
        }

        $refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($refererHost === '' || in_array($refererHost, ['localhost', '127.0.0.1', 'nsync.localhost'], true)) {
            return null;
        }

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';

        return "http://{$refererHost}{$portSegment}/auth/host-logout";
    }
}
