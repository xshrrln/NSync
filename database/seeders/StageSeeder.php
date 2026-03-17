<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Stage;
use Illuminate\Database\Seeder;

class StageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Board::whereDoesntHave('stages')->chunk(50, function ($boards) {
            foreach ($boards as $board) {
                Stage::create([
                    'board_id' => $board->id,
                    'name' => 'To Do',
                    'position' => 1,
                ]);
                Stage::create([
                    'board_id' => $board->id,
                    'name' => 'In Progress',
                    'position' => 2,
                ]);
                Stage::create([
                    'board_id' => $board->id,
                    'name' => 'Done',
                    'position' => 3,
                ]);
            }
        });
    }
}
