<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Models\AppSetting;
use Illuminate\Support\Carbon;

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

        // Dispatch job to create tenant database and run migrations
        dispatch(new \App\Jobs\CreateTenantDatabase($tenant));

        // Generate temporary password
        $temporaryPassword = \Str::random(12);
        
        // Update tenant status
        $tenant->update(['status' => 'active']);

        // Set password for the tenant admin user
        $tenant->users()->first()?->update([
            'password' => \Hash::make($temporaryPassword)
        ]);

        // Send approval email with credentials
        try {
            \Mail::to($tenant->tenant_admin_email)->send(new \App\Mail\TenantApproved($tenant, $temporaryPassword));
        } catch (\Exception $e) {
            // Log mail error but don't fail approval
            \Log::warning('Approval email failed to send: ' . $e->getMessage());
        }

        return back()->with('success', 'Tenant approved! Database creation in progress. Credentials email sent to ' . $tenant->tenant_admin_email);
    }

    public function reject(Tenant $tenant)
    {
        $this->authorize('approve', $tenant);

        // Update status to disabled
        $tenant->update(['status' => 'disabled']);

        // Send rejection email
        try {
            \Mail::to($tenant->tenant_admin_email)->send(new \App\Mail\TenantRejected($tenant));
        } catch (\Exception $e) {
            \Log::warning('Rejection email failed to send: ' . $e->getMessage());
        }

        return back()->with('success', 'Tenant registration rejected. Notification email sent to ' . $tenant->tenant_admin_email);
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

        try {
            \Mail::to($tenant->tenant_admin_email)->send(new \App\Mail\TenantSuspended($tenant, $reason));
        } catch (\Exception $e) {
            \Log::warning('Suspension email failed: ' . $e->getMessage());
        }

        return back()->with('success', "Tenant '{$tenant->name}' suspended. Notification sent.");
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

        $request->validate([
            'plan' => 'required|in:free,standard,pro'
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
}
