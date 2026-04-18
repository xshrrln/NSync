<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'organization' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:500'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(12)),
        ]);

        $domain = Str::slug($request->organization) . '.localhost';
        $database = Str::slug($request->organization) . '_db';

        $tenant = Tenant::create([
            'organization' => $request->organization,
            'name' => $request->organization,
            'address' => $request->address,
            'tenant_admin' => $request->name,
            'tenant_admin_email' => $request->email,
            'domain' => $domain,
            'database' => $database,
            'plan' => 'free',
            'status' => 'pending',
        ]);

        $user->update(['tenant_id' => $tenant->id]);
        $user->assignRole('Team Supervisor');

        // Dispatch tenant database creation
        \Bus::dispatch(new \App\Jobs\CreateTenantDatabase($tenant, [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]));

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('pending-approval', $tenant->id));
    }
}

