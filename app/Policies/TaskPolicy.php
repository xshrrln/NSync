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
        return $user->tenant_id !== null;
    }

    public function update(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id;
    }

    public function delete(User $user, Task $task): bool
    {
        return $user->tenant_id === $task->tenant_id && ($user->hasRole('Team Supervisor') || $task->user_id === $user->id);
    }

    public function move(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }
}

