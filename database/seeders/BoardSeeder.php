<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\User;
use Illuminate\Database\Seeder;

class BoardSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrFail(); // Seed for first user

        Board::create([
            'user_id' => $user->id,
            'name' => 'Welcome Project',
            'slug' => 'welcome-project-' . uniqid(),
        ]);

        Board::create([
            'user_id' => $user->id,
            'name' => 'My Kanban Board',
            'slug' => 'kanban-board-' . uniqid(),
        ]);
    }
}

