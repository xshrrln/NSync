<?php

namespace App\Http\Requests\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = User::withoutGlobalScopes()
            ->where('email', $this->string('email')->toString())
            ->first();

        if (!$user) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'User does not exist.',
            ]);
        }

        if (!Hash::check($this->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'password' => 'Incorrect email or password.',
            ]);
        }

        Auth::login($user, $this->boolean('remember'));
        $host = strtolower($this->getHost());

        // Admin URL allows system admins only.
        if ($this->isAdminHost($host) && !$user->hasRole('Platform Administrator')) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => 'This login URL is for system administrators only.',
            ]);
        }

        // Tenant URL allows only users belonging to that tenant.
        $tenant = $this->detectTenantForLogin();
        if (!$tenant && !$this->isCentralHost($host) && !$this->isAdminHost($host)) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'Workspace URL not recognized.',
            ]);
        }

        if ($tenant && (! $user->belongsToTenant($tenant) || $user->hasRole('Platform Administrator'))) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            throw ValidationException::withMessages([
                'email' => 'User does not exist. Make sure you belong in this organization.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Detect tenant from host/path for login (mimic IdentifyTenant logic).
     */
    private function detectTenantForLogin(): ?Tenant
    {
        $host = strtolower($this->getHost());

        if ($this->isCentralHost($host) || $this->isAdminHost($host)) {
            return null;
        }

        return Tenant::where('domain', $host)->first()
            ?? Tenant::where('domain', str_replace('www.', '', $host))->first();
    }

    private function isCentralHost(string $host): bool
    {
        return in_array($host, ['127.0.0.1', 'localhost'], true);
    }

    private function isAdminHost(string $host): bool
    {
        return $host === 'nsync.localhost';
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
