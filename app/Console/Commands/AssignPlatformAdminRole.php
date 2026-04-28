<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class AssignPlatformAdminRole extends Command
{
    protected $signature = 'admin:assign-role {user-id? : The user ID to assign the role to}';
    protected $description = 'Assign Platform Administrator role to a user';

    public function handle()
    {
        $userId = $this->argument('user-id');

        if (!$userId) {
            // Show all users
            $users = User::all();
            $this->info('Available users:');
            foreach ($users as $user) {
                $roles = $user->getRoleNames()->implode(', ') ?: 'No roles';
                $this->line("  ID: {$user->id}, Name: {$user->name}, Email: {$user->email}, Roles: {$roles}");
            }

            $userId = $this->ask('Enter user ID to assign Platform Administrator role');
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found!");
            return 1;
        }

        $role = Role::firstOrCreate(
            ['name' => 'Platform Administrator', 'guard_name' => 'web']
        );

        if ($user->hasRole('Platform Administrator')) {
            $this->info("User {$user->name} already has the Platform Administrator role.");
            return 0;
        }

        $user->assignRole($role);
        $this->info("Successfully assigned Platform Administrator role to {$user->name}!");

        return 0;
    }
}
