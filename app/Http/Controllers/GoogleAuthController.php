<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirectToGoogle(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->with('error', 'Google sign-in is not configured yet.');
        }

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('login')->with('error', 'Google sign-in was cancelled or denied.');
        }

        try {
            $googleUser = Socialite::driver('google')->stateless()->user();
        } catch (Exception $e) {
            Log::error('Google OAuth callback failed.', [
                'message' => $e->getMessage(),
            ]);

            return redirect()->route('login')->with('error', 'Google login failed. Please try again.');
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();

        if ($email === '') {
            return redirect()->route('login')->with('error', 'Google account has no verified email.');
        }

        $user = User::withoutGlobalScopes()
            ->where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if (! $user) {
            return $this->redirectToRegistration($googleUser->getName(), $email);
        }

        $user->forceFill([
            'name' => $googleUser->getName() ?: $user->name,
            'avatar' => $googleUser->getAvatar(),
            'google_id' => $googleId,
            'email_verified_at' => $user->email_verified_at ?: Carbon::now(),
        ])->save();

        Auth::login($user, true);
        $request->session()->regenerate();

        if ($this->isPlatformAdmin($user)) {
            return $this->redirectToDestination($request, $user, $this->adminUrl());
        }

        if (! $user->tenant) {
            Auth::logout();

            return $this->redirectToRegistration($googleUser->getName(), $email, true);
        }

        return $this->redirectToDestination($request, $user, $this->tenantUrl($user->tenant->domain));
    }

    private function redirectToRegistration(?string $name, string $email, bool $existingAccount = false): RedirectResponse
    {
        session([
            'registration_data' => [
                'org_name' => (($name ?: 'New Org') . ' Workspace'),
                'org_address' => '',
                'org_domain' => $this->suggestDomain($email),
                'name' => $name ?: Str::before($email, '@'),
                'email' => $email,
                'plan' => 'free',
            ],
        ]);

        return redirect()
            ->route('register')
            ->with(
                'info',
                $existingAccount
                    ? 'Your Google account is connected. Complete your workspace setup to continue.'
                    : 'We prefilled your registration with Google details. Please complete your workspace setup.'
            );
    }

    private function redirectToDestination(Request $request, User $user, string $destination): RedirectResponse
    {
        $destinationHost = $this->hostForUrl($destination);
        $currentHost = strtolower($request->getHost());

        if ($destinationHost !== $currentHost) {
            return redirect()->to($this->createHandoffUrl($request, $user, $destination));
        }

        return redirect()->to($destination);
    }

    private function createHandoffUrl(Request $request, User $user, string $destination): string
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

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: $request->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        $queryString = http_build_query(['token' => $token]);

        return "http://{$host}{$portSegment}/auth/handoff?{$queryString}";
    }

    private function tenantUrl(?string $domain): string
    {
        if (! $domain) {
            return route('dashboard');
        }

        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: request()->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';
        $host = str_contains($domain, '.') ? $domain : "{$domain}.localhost";

        return "http://{$host}{$portSegment}/dashboard";
    }

    private function adminUrl(): string
    {
        $port = parse_url(config('app.url'), PHP_URL_PORT) ?: request()->getPort() ?: 8000;
        $portSegment = $port && $port !== 80 && $port !== 443 ? ':' . $port : '';

        return "http://nsync.localhost{$portSegment}/dashboard";
    }

    private function hostForUrl(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    private function isPlatformAdmin(User $user): bool
    {
        return $user->email === 'admin@nsync.com' || $user->hasRole('Platform Administrator');
    }

    private function suggestDomain(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '-');
        $base = $base ?: 'workspace';
        $candidate = $base;
        $counter = 2;

        while (Tenant::where('domain', $candidate . '.localhost')->exists()) {
            $candidate = $base . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }
}
