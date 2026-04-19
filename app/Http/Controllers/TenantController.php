<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\AppSetting;
use Illuminate\Support\Carbon;
use Throwable;

class TenantController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants',
        ]);

        $settings = AppSetting::data();
        $defaultPlan = $settings['default_plan'] ?? 'free';
        $tenant = Tenant::create([
            'organization' => $request->name,
            'name' => $request->name,
            'domain' => $request->domain . '.localhost',
            'plan' => $defaultPlan,
            'status' => 'pending',
            'start_date' => now(),
            'due_date' => now()->addDays($this->planDurationDays($defaultPlan)),
        ]);

        // Assign creator as tenant owner
        $user = $request->user();
        $user->update(['tenant_id' => $tenant->id]);
        $ownerRole = Role::findByName('Team Supervisor');
        $user->assignRole($ownerRole);

        // Notify platform admin if enabled
        if ($settings['notify_new_tenant'] ?? false) {
            $admin = \App\Models\User::role('Platform Administrator')->first();
            if ($admin) {
                \Mail::raw("A new tenant '{$tenant->name}' was created with plan '{$defaultPlan}'.", function ($message) use ($admin) {
                    $message->to($admin->email)->subject('New tenant created');
                });
            }
        }

        return redirect()->route('dashboard')->with('success', 'Tenant created. Awaiting admin approval.');
    }

    public function approve(Tenant $tenant)
    {
        $this->authorize('approve', $tenant);

        // Generate temporary password
        $temporaryPassword = \Str::random(12);

        try {
            DB::transaction(function () use ($tenant, $temporaryPassword) {
                $tenant->refresh();

                $tenantAdmin = $tenant->users()->first();
                if (! $tenantAdmin) {
                    throw new \RuntimeException('Tenant admin user not found for approval flow.');
                }

                $tenantAdmin->update([
                    'password' => \Hash::make($temporaryPassword),
                ]);

                $emailSent = $this->sendTenantEmail(
                    $tenant->tenant_admin_email,
                    new \App\Mail\TenantApproved($tenant, $temporaryPassword),
                    'tenant_approval_credentials',
                    $tenant
                );

                if (! $emailSent) {
                    throw new \RuntimeException('Credentials email delivery failed during tenant approval.');
                }

                $tenant->update(['status' => 'active']);
            });
        } catch (Throwable $exception) {
            Log::warning('Tenant approval aborted.', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'Tenant approval was not completed because credentials email could not be sent. No tenant database was provisioned.');
        }

        try {
            dispatch(new \App\Jobs\CreateTenantDatabase($tenant->fresh()));
        } catch (Throwable $exception) {
            Log::error('Tenant database provisioning dispatch failed after approval.', [
                'tenant_id' => $tenant->id,
                'tenant_name' => $tenant->name,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('warning', 'Tenant credentials were sent and status is active, but tenant database provisioning could not start. Please retry provisioning.');
        }

        return back()->with('success', 'Tenant approved! Database creation in progress. Credentials email sent to ' . $tenant->tenant_admin_email);
    }

    public function reject(Tenant $tenant)
    {
        $this->authorize('approve', $tenant);

        // Update status to disabled
        $tenant->update(['status' => 'disabled']);

        $emailSent = $this->sendTenantEmail(
            $tenant->tenant_admin_email,
            new \App\Mail\TenantRejected($tenant),
            'tenant_rejection',
            $tenant
        );

        if ($emailSent) {
            return back()->with('success', 'Tenant registration rejected. Notification email sent to ' . $tenant->tenant_admin_email);
        }

        return back()->with('warning', 'Tenant registration rejected, but rejection email could not be delivered. Please verify mail settings.');
    }

    public function index()
    {
        $tenants = Tenant::with('users')
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('admin.tenants.index', compact('tenants'));
    }

    public function edit(Tenant $tenant)
    {
        $this->authorize('update', $tenant);
        $tenant->load('users');

        $plans = config('plans');
        $planKey = strtolower($tenant->plan ?? 'free');
        $planFeatures = $plans[$planKey]['features'] ?? [];
        $allFeatures = collect(config('features.categories'))
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->values()
            ->all();

        $actions = is_array($tenant->actions)
            ? $tenant->actions
            : ($tenant->actions ? json_decode((string) $tenant->actions, true) ?: [] : []);
        $enabledFeatures = array_keys(array_filter($actions)) ?: $planFeatures; // default to plan features

        return view('admin.tenants.edit', [
            'tenant' => $tenant,
            'allFeatures' => $allFeatures,
            'enabledFeatures' => $enabledFeatures,
            'planFeatures' => $planFeatures,
            'featureCategories' => config('features.categories'),
        ]);
    }

    public function update(Tenant $tenant, Request $request)
    {
        $this->authorize('update', $tenant);

        if ($request->boolean('tenant_details')) {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'address' => 'nullable|string|max:500',
                'tenant_admin' => 'required|string|max:255',
                'tenant_admin_email' => 'required|email|max:255',
                'domain' => [
                    'required',
                    'string',
                    'max:255',
                    'regex:/^[a-z0-9.-]+$/',
                    function (string $attribute, mixed $value, \Closure $fail) use ($tenant) {
                        $normalized = str_contains((string) $value, '.')
                            ? strtolower((string) $value)
                            : strtolower((string) $value) . '.localhost';

                        $exists = Tenant::where('id', '!=', $tenant->id)
                            ->where('domain', $normalized)
                            ->exists();

                        if ($exists) {
                            $fail('The workspace domain has already been taken.');
                        }
                    },
                ],
                'status' => 'required|in:pending,active,disabled',
                'start_date' => 'nullable|date',
            ]);

            $startDate = !empty($validated['start_date'])
                ? Carbon::parse($validated['start_date'])
                : ($tenant->start_date ? Carbon::parse($tenant->start_date) : now());
            $dueDate = $startDate->copy()->addDays($this->planDurationDays($tenant->plan ?? 'free'));

            $domain = str_contains($validated['domain'], '.')
                ? strtolower($validated['domain'])
                : strtolower($validated['domain']) . '.localhost';

            $tenant->update([
                'organization' => $validated['name'],
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'tenant_admin' => $validated['tenant_admin'],
                'tenant_admin_email' => $validated['tenant_admin_email'],
                'domain' => $domain,
                'status' => $validated['status'],
                'start_date' => $startDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
            ]);

            return back()->with('success', 'Tenant details updated for ' . $tenant->name);
        }

        $validated = $request->validate([
            'features' => 'array',
            'features.*' => 'boolean'
        ]);

        $plans = config('plans');
        $planKey = strtolower($tenant->plan ?? 'free');
        $planFeatures = $plans[$planKey]['features'] ?? [];
        $selected = array_keys(array_filter($validated['features'] ?? []));
        $catalog = collect(config('features.categories'))
            ->flatMap(fn ($category) => array_keys($category['features'] ?? []))
            ->unique()
            ->values()
            ->all();
        $selected = array_values(array_intersect($selected, $catalog));

        // Always include plan features, then add any extra toggles selected.
        $finalFeatures = array_unique(array_merge($planFeatures, $selected));
        $existingActions = is_array($tenant->actions)
            ? $tenant->actions
            : ($tenant->actions ? json_decode((string) $tenant->actions, true) ?: [] : []);

        $preservedSettings = collect($existingActions)
            ->reject(fn ($value, $key) => in_array($key, $catalog, true))
            ->all();

        $actions = $preservedSettings;
        foreach ($finalFeatures as $feature) {
            $actions[$feature] = true;
        }

        $tenant->update([
            'actions' => $actions
        ]);

        return redirect()->route('admin.tenants.index')->with('success', 'Features updated for ' . $tenant->name);
    }

    public function suspend(Tenant $tenant, Request $request)
    {
        $this->authorize('suspend', $tenant);

        $reason = $request->input('reason', 'Administrative suspension');
        $tenant->update(['status' => 'disabled']);

        $emailSent = $this->sendTenantEmail(
            $tenant->tenant_admin_email,
            new \App\Mail\TenantSuspended($tenant, $reason),
            'tenant_suspension',
            $tenant
        );

        if ($emailSent) {
            return back()->with('success', "Tenant '{$tenant->name}' suspended. Notification sent.");
        }

        return back()->with('warning', "Tenant '{$tenant->name}' suspended, but suspension email could not be delivered.");
    }

    public function resume(Tenant $tenant)
    {
        $this->authorize('suspend', $tenant);

        $tenant->update(['status' => 'active']);

        return back()->with('success', "Tenant '{$tenant->name}' resumed.");
    }

    public function upgradePlan(Tenant $tenant, Request $request)
    {
        $this->authorize('update', $tenant);

        $availablePlans = array_keys((array) config('plans', []));
        if ($availablePlans === []) {
            $availablePlans = ['free', 'standard', 'pro'];
        }
        $request->validate([
            'plan' => ['required', 'string', 'in:' . implode(',', $availablePlans)]
        ]);

        $oldPlan = $tenant->plan;
        $tenant->update([
            'plan' => $request->plan,
            'start_date' => now()->toDateString(),
            'due_date' => now()->addDays($this->planDurationDays($request->plan))->toDateString(),
        ]);

        // Log subscription change (stub for Stripe webhook)
        \Log::info('Plan upgraded', [
            'tenant_id' => $tenant->id,
            'old_plan' => $oldPlan,
            'new_plan' => $request->plan
        ]);

        return back()->with('success', "Plan upgraded to {$request->plan}. Features updated immediately.");
    }

    private function planDurationDays(string $plan): int
    {
        return strtolower($plan) === 'free' ? 14 : 30;
    }

    /**
     * Attempt to send tenant mail via available mailers and report real delivery status.
     */
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
