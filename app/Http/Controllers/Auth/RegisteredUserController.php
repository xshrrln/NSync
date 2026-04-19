<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        $rawPlans = (array) config('plans', []);
        $plans = collect($rawPlans)
            ->mapWithKeys(function (array $plan, string $key) {
                return [$key => array_merge([
                    'name' => ucfirst($key) . ' Plan',
                ], $plan)];
            })
            ->all();

        $prefill = [
            'plan' => request()->query('plan', 'free'),
            'org_name' => (string) request()->query('org_name', ''),
            'org_address' => (string) request()->query('org_address', ''),
            'org_domain' => (string) request()->query('org_domain', ''),
            'name' => (string) request()->query('name', ''),
            'email' => (string) request()->query('email', ''),
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

        return redirect()->route('register.billing');
    }

    public function showBilling(Request $request): View|RedirectResponse
    {
        $data = (array) $request->session()->get('registration_data', []);
        if ($data === []) {
            return redirect()->route('register')->with('warning', 'Please complete organization details first.');
        }

        return view('auth.register-billing', [
            'data' => $data,
        ]);
    }

    public function completeBilling(Request $request): RedirectResponse
    {
        $data = (array) $request->session()->get('registration_data', []);
        if ($data === []) {
            return redirect()->route('register')->with('warning', 'Registration session expired. Please start again.');
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
            return redirect()->route('register')->with('warning', 'Registration data is incomplete. Please start again.');
        }

        if (User::where('email', $email)->exists()) {
            return redirect()->route('register')->withErrors([
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

        if (Tenant::where('domain', $domain)->exists()) {
            return back()
                ->withErrors(['card_holder' => 'The selected workspace domain is already taken. Please go back and choose another domain.'])
                ->withInput();
        }

        try {
            $user = DB::transaction(function () use ($data, $email, $organization, $domain, $plan, $request) {
                $user = User::create([
                    'name' => (string) ($data['name'] ?? ''),
                    'email' => $email,
                    'password' => Hash::make(Str::random(12)),
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
                    'status' => 'pending',
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

                return $user->fresh();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return back()
                ->withErrors(['card_holder' => 'Registration could not be completed. No tenant account or database was created. Please try again.'])
                ->withInput();
        }

        event(new Registered($user));
        Auth::login($user);
        $request->session()->forget('registration_data');

        return redirect()->route('pending-approval')->with('success', 'Registration submitted with billing details. Awaiting admin approval.');
    }
}
