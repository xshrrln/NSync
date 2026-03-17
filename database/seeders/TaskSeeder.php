<?php

namespace Database\Seeders;

use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrFail();
        
        // Create sample tasks for each board and stage
        Board::where('user_id', $user->id)->each(function ($board) use ($user) {
            $stages = Stage::where('board_id', $board->id)->orderBy('position')->get();
            
            $sampleTasks = [
                ['title' => 'Setup project structure', 'stage' => 0],
                ['title' => 'Design database schema', 'stage' => 0],
                ['title' => 'Implement authentication', 'stage' => 1],
                ['title' => 'Create API endpoints', 'stage' => 1],
                ['title' => 'Write unit tests', 'stage' => 2],
                ['title' => 'Deploy to production', 'stage' => 2],
            ];
            
            foreach ($sampleTasks as $index => $taskData) {
                $stageIndex = $taskData['stage'] % count($stages);
                
                Task::create([
                    'title' => $taskData['title'],
                    'board_id' => $board->id,
                    'stage_id' => $stages[$stageIndex]->id,
                    'user_id' => $user->id,
                    'position' => $index + 1,
                ]);
            }
        });
    }
}


