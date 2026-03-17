<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class TenantController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants',
        ]);

        $tenant = Tenant::create([
            'name' => $request->name,
            'domain' => $request->domain,
            'plan' => 'free',
            'status' => 'pending',
        ]);

        // Assign creator as tenant owner
        $user = $request->user();
        $user->update(['tenant_id' => $tenant->id]);
        $ownerRole = Role::findByName('Team Supervisor');
        $user->assignRole($ownerRole);

        return redirect()->route('dashboard')->with('success', 'Tenant created. Awaiting admin approval.');
    }

    public function approve(Tenant $tenant)
    {
        $this->authorize('admin'); // Define in policy

        $tenant->update(['status' => 'approved']);

        return back()->with('success', 'Tenant approved.');
    }

    public function index()
    {
        return view('admin.tenants.index', [
            'tenants' => Tenant::with('users')->paginate(10)
        ]);
    }
}

