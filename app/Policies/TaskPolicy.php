<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id;
    }

    public function create(User $user): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;

        return $user->tenant_id !== null
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal();
    }

    public function update(User $user, Task $task): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;

        return $user->tenant_id === $task->tenant_id
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal();
    }

    public function delete(User $user, Task $task): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;

        return $user->tenant_id === $task->tenant_id
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal()
            && ($user->hasRole('Team Supervisor') || $task->user_id === $user->id);
    }

    public function move(User $user, Task $task): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;

        return $user->tenant_id === $task->tenant_id
            && $tenant
            && ! $tenant->requiresSubscriptionRenewal(); // All members can move cards
    }

    public function invite(User $user): bool
    {
        $tenant = app()->has('currentTenant') ? app('currentTenant') : $user->tenant;
        return $tenant
            && ! $tenant->requiresSubscriptionRenewal()
            && $tenant->hasFeature('member-invites')
            && $user->hasRole('Team Supervisor');
    }

    public function billing(User $user): bool
    {
        $tenant = app('currentTenant') ?? $user->tenant;
        return $tenant
            && ($user->hasRole('Team Supervisor') || $tenant->hasFeature('advanced-reporting'));
    }
}

