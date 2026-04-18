<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (! $this->isTwoFactorRequired($request)) {
            return redirect()->intended(route('dashboard'));
        }

        if ($this->isVerified($request)) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.two-factor-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isTwoFactorRequired($request)) {
            return redirect()->intended(route('dashboard'));
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $sessionCode = (string) $request->session()->get('two_factor.code', '');
        $expiresAt = (int) $request->session()->get('two_factor.expires_at', 0);
        $inputCode = (string) $request->input('code');

        if ($sessionCode === '' || time() > $expiresAt || ! hash_equals($sessionCode, $inputCode)) {
            return back()->withErrors(['code' => 'Invalid or expired verification code.']);
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $request->user();
        if (! $tenant || ! $user) {
            return redirect()->route('login');
        }

        $request->session()->put($this->verifiedSessionKey($tenant, $user), [
            'verified_at' => time(),
            'frequency' => $tenant->twoFactorSettings()['frequency'],
        ]);
        $request->session()->forget('two_factor');

        $response = redirect()->intended(route('dashboard'));

        if (($tenant->twoFactorSettings()['frequency'] ?? 'new_device') === 'new_device') {
            $response->cookie(
                $this->trustedDeviceCookieName($tenant, $user),
                $this->trustedDeviceToken($request, $tenant, $user),
                60 * 24 * 180, // 180 days
                '/',
                null,
                (bool) $request->isSecure(),
                true,
                false,
                'lax'
            );
        }

        return $response;
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $tenant || ! $tenant->isTwoFactorEnforcedForUser($user)) {
            return redirect()->route('dashboard');
        }

        $code = (string) random_int(100000, 999999);
        $ttlMinutes = $tenant->twoFactorCodeTtlMinutes();
        $expiresAt = time() + ($ttlMinutes * 60);

        $request->session()->put('two_factor.code', $code);
        $request->session()->put('two_factor.expires_at', $expiresAt);
        $request->session()->put('two_factor.sent_at', time());

        Mail::raw(
            "Your NSync verification code is {$code}. It expires in {$ttlMinutes} minutes.",
            fn ($message) => $message->to($user->email)->subject('Your NSync verification code')
        );

        return back()->with('status', 'A new verification code was sent.');
    }

    private function isVerified(Request $request): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $request->user();
        if (! $tenant || ! $user) {
            return false;
        }

        $state = $request->session()->get($this->verifiedSessionKey($tenant, $user));
        $window = $tenant->twoFactorVerificationWindowSeconds();

        if ($this->hasTrustedDeviceCookie($request, $tenant, $user)) {
            return true;
        }

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
            $request->session()->forget($this->verifiedSessionKey($tenant, $user));
        }

        return $valid;
    }

    private function isTwoFactorRequired(Request $request): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $user = $request->user();

        return $tenant instanceof Tenant
            && $user instanceof User
            && $tenant->isTwoFactorEnforcedForUser($user);
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

        $cookieValue = (string) $request->cookie($this->trustedDeviceCookieName($tenant, $user), '');
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
