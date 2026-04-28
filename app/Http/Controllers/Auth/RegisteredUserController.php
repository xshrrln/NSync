<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Jobs\CreateTenantDatabase;
use App\Mail\TenantApproved;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AdminAuditLogger;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $host = strtolower(request()->getHost());
        $isLocalhost = in_array($host, ['localhost', '127.0.0.1'], true);

        if ($isLocalhost && Auth::check()) {
            Auth::guard('web')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $rawPlans = (array) config('plans', []);
        $plans = collect($rawPlans)
            ->mapWithKeys(function (array $plan, string $key) {
                return [$key => array_merge([
                    'name' => ucfirst($key) . ' Plan',
                ], $plan)];
            })
            ->all();

        $sessionRegistrationData = (array) request()->session()->get('registration_data', []);
        $prefill = [
            'plan' => request()->query('plan', 'free'),
            'org_name' => (string) request()->query('org_name', ''),
            'org_address' => (string) request()->query('org_address', ''),
            'org_domain' => (string) request()->query('org_domain', ''),
            'name' => (string) request()->query('name', $sessionRegistrationData['name'] ?? ''),
            'email' => (string) request()->query('email', $sessionRegistrationData['email'] ?? ''),
        ];

        return view('auth.register', [
            'plans' => $plans,
            'prefill' => $prefill,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $organization = (string) ($request->input('org_name') ?? $request->input('organization') ?? '');
        $address = (string) ($request->input('org_address') ?? $request->input('address') ?? '');
        $domainInput = (string) ($request->input('org_domain') ?? '');

        if ($domainInput === '') {
            $domainInput = Str::slug($organization);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'plan' => ['nullable', 'string'],
            'org_name' => ['nullable', 'string', 'max:255'],
            'organization' => ['nullable', 'string', 'max:255'],
            'org_address' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:500'],
            'org_domain' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
        ]);

        if ($organization === '' || $address === '') {
            $errors = [];
            if ($organization === '') {
                $errors['org_name'] = 'Organization name is required.';
            }
            if ($address === '') {
                $errors['org_address'] = 'Organization address is required.';
            }

            return back()
                ->withErrors($errors)
                ->withInput();
        }

        $allowedPlans = array_keys((array) config('plans', []));
        $selectedPlan = Str::lower((string) $request->input('plan', 'free'));
        if (! in_array($selectedPlan, $allowedPlans, true)) {
            $selectedPlan = 'free';
        }

        $request->session()->put('registration_data', [
            'name' => (string) $request->name,
            'email' => (string) $request->email,
            'org_name' => $organization,
            'org_address' => $address,
            'org_domain' => Str::slug($domainInput),
            'plan' => $selectedPlan,
        ]);

        return redirect()->to(route('register.billing', absolute: false));
    }

    public function showBilling(Request $request): View|RedirectResponse
    {
        $data = (array) $request->session()->get('registration_data', []);
        if ($data === []) {
            return redirect()->to(route('register', absolute: false))->with('warning', 'Please complete organization details first.');
        }

        return view('auth.register-billing', [
            'data' => $data,
        ]);
    }

    public function completeBilling(Request $request, AdminAuditLogger $auditLogger): RedirectResponse
    {
        $data = (array) $request->session()->get('registration_data', []);
        if ($data === []) {
            return redirect()->to(route('register', absolute: false))->with('warning', 'Registration session expired. Please start again.');
        }

        $request->validate([
            'card_holder' => ['required', 'string', 'max:255'],
            'card_number' => ['required', 'string', 'regex:/^\d{4}\s?\d{4}\s?\d{4}\s?\d{4}$/'],
            'card_expiry' => ['required', 'string', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'card_cvv' => ['required', 'string', 'regex:/^\d{3,4}$/'],
            'billing_country' => ['required', 'string', 'max:120'],
        ]);

        $email = (string) ($data['email'] ?? '');
        if ($email === '') {
            return redirect()->to(route('register', absolute: false))->with('warning', 'Registration data is incomplete. Please start again.');
        }

        if (User::where('email', $email)->exists()) {
            return redirect()->to(route('register', absolute: false))->withErrors([
                'email' => 'This email is already registered.',
            ]);
        }

        $organization = (string) ($data['org_name'] ?? '');
        $domain = Str::slug((string) ($data['org_domain'] ?? '')) . '.localhost';
        $plan = Str::lower((string) ($data['plan'] ?? 'free'));
        $allowedPlans = array_keys((array) config('plans', []));
        if (! in_array($plan, $allowedPlans, true)) {
            $plan = 'free';
        }

        $temporaryPassword = Str::random(12);

        if (Tenant::where('domain', $domain)->exists()) {
            return back()
                ->withErrors(['card_holder' => 'The selected workspace domain is already taken. Please go back and choose another domain.'])
                ->withInput();
        }

        try {
            [$user, $tenant] = DB::transaction(function () use ($data, $email, $organization, $domain, $plan, $request, $temporaryPassword) {
                $user = User::create([
                    'name' => (string) ($data['name'] ?? ''),
                    'email' => $email,
                    'password' => Hash::make($temporaryPassword),
                ]);

                $tenant = Tenant::create([
                    'organization' => $organization,
                    'name' => $organization,
                    'address' => (string) ($data['org_address'] ?? ''),
                    'tenant_admin' => (string) ($data['name'] ?? ''),
                    'tenant_admin_email' => $email,
                    'domain' => $domain,
                    'database' => Tenant::generateDatabaseName($organization, null),
                    'plan' => $plan,
                    'status' => 'active',
                    'start_date' => now()->toDateString(),
                    'due_date' => now()->addDays($this->planDurationDays($plan))->toDateString(),
                    'billing_data' => [
                        'cardholder_name' => (string) $request->card_holder,
                        'card_last_four' => substr(preg_replace('/\D+/', '', (string) $request->card_number), -4),
                        'card_expiry' => (string) $request->card_expiry,
                        'billing_country' => (string) $request->billing_country,
                    ],
                ]);

                $resolvedDbName = Tenant::generateDatabaseName($tenant->name, $tenant->id);
                if ($tenant->database !== $resolvedDbName) {
                    $tenant->updateQuietly(['database' => $resolvedDbName]);
                }

                $user->update(['tenant_id' => $tenant->id]);
                $user->assignRole('Team Supervisor');

                return [$user->fresh(), $tenant->fresh()];
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['card_holder' => 'Registration could not be completed. No tenant account or database was created. Please try again.'])
                ->withInput();
        }

        try {
            (new CreateTenantDatabase($tenant, [
                'name' => $user->name,
                'email' => $user->email,
                'password' => Hash::make($temporaryPassword),
                'role' => 'Team Supervisor',
            ]))->handle();
        } catch (Throwable $exception) {
            report($exception);

            $tenant->updateQuietly(['status' => 'disabled']);

            return back()
                ->withErrors(['card_holder' => 'Payment was recorded, but workspace provisioning did not finish. Please contact support before retrying.'])
                ->withInput();
        }

        if (! $this->sendTenantEmail(
            $tenant->tenant_admin_email,
            new TenantApproved($tenant, $temporaryPassword),
            'tenant_signup_credentials',
            $tenant
        )) {
            Log::warning('Tenant signup completed but credentials email could not be delivered.', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'recipient' => $tenant->tenant_admin_email,
            ]);

            return back()
                ->withErrors(['card_holder' => 'Workspace was created, but the credentials email could not be delivered. Please contact support.'])
                ->withInput();
        }

        event(new Registered($user));

        $auditLogger->log(
            user: $user,
            action: 'Tenant subscription started',
            description: "Started a {$plan} subscription for {$organization}.",
            request: $request,
            context: [
                'audience' => 'tenant',
                'tenant_id' => $tenant?->id,
                'tenant_name' => $tenant?->name,
                'tenant_domain' => $tenant?->domain,
                'selected_plan' => $plan,
                'billing_country' => (string) $request->billing_country,
                'initial_status' => $tenant?->status,
            ],
            statusCode: 302,
            subjectType: $tenant ? 'Tenant' : null,
            subjectId: $tenant?->id,
        );

        $request->session()->forget('registration_data');
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(route('login', absolute: false))->with('success', 'Workspace created successfully. Your login credentials and workspace URL have been sent to your email.');
    }

    private function planDurationDays(string $plan): int
    {
        return strtolower($plan) === 'free' ? 14 : 30;
    }

    private function sendTenantEmail(?string $recipient, object $mailable, string $event, Tenant $tenant): bool
    {
        $recipient = trim((string) $recipient);
        if ($recipient === '') {
            Log::warning('Tenant mail skipped: missing recipient email.', [
                'event' => $event,
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
            ]);

            return false;
        }

        $mailersToTry = array_values(array_unique(array_filter([
            config('mail.default'),
            'smtp',
            'failover',
        ], fn ($value) => is_string($value) && trim($value) !== '')));

        $lastError = null;

        foreach ($mailersToTry as $mailer) {
            try {
                Mail::mailer($mailer)->to($recipient)->send($mailable);

                Log::info('Tenant mail sent.', [
                    'event' => $event,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'recipient' => $recipient,
                    'mailer' => $mailer,
                ]);

                return true;
            } catch (Throwable $exception) {
                $lastError = $exception;

                Log::warning('Tenant mail send attempt failed.', [
                    'event' => $event,
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'recipient' => $recipient,
                    'mailer' => $mailer,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        Log::error('Tenant mail failed after all mailers.', [
            'event' => $event,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'recipient' => $recipient,
            'mailers_tried' => $mailersToTry,
            'last_error' => $lastError?->getMessage(),
        ]);

        return false;
    }
}
