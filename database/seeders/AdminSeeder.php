<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'admin@nsync.com',
            'password' => bcrypt('password'),
            'tenant_id' => null, // Global admin
            'email_verified_at' => now(),
        ]);

        $adminRole = Role::findByName('Platform Administrator');
        $admin->assignRole($adminRole);
    }
}

