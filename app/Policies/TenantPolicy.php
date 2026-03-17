<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id || $user->hasRole('Platform Administrator');
    }

    public function create(User $user): bool
    {
        return true; // via tenant-request
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $user->tenant_id === $tenant->id && $user->hasRole('Team Supervisor');
    }

    public function approve(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('Platform Administrator');
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('Platform Administrator');
    }
}

