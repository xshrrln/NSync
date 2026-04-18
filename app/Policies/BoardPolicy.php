<?php

namespace App\Policies;

use App\Models\Board;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BoardPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Board $board): bool
    {
        return $user->tenant_id === $board->tenant_id;
    }

    public function create(User $user): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;
        if (!$tenant) {
            return false;
        }

        if ($tenant->requiresSubscriptionRenewal()) {
            return false;
        }

        $canCreate = $tenant->hasFeature('board-creation');
        $underLimit = $tenant->hasFeature('unlimited-boards') || !$tenant->hasReachedLimit('boards');

        return $canCreate && $underLimit && $user->hasRole('Team Supervisor');
    }

    public function update(User $user, Board $board): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;
        return $user->tenant_id === $board->tenant_id
            && $user->hasRole('Team Supervisor')
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal()
            && $tenant->hasFeature('role-permissions');
    }

    public function delete(User $user, Board $board): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;
        return $user->tenant_id === $board->tenant_id
            && $user->hasRole('Team Supervisor')
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal()
            && $tenant->hasFeature('role-permissions');
    }
}

