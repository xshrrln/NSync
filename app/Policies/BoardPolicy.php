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
        return $user->hasRole('Team Supervisor');
    }

    public function update(User $user, Board $board): bool
    {
        return $user->tenant_id === $board->tenant_id && $user->hasRole('Team Supervisor');
    }

    public function delete(User $user, Board $board): bool
    {
        return $user->tenant_id === $board->tenant_id && $user->hasRole('Team Supervisor');
    }
}

