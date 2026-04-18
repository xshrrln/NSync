<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Task;
use App\Models\Board;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('boards.{boardId}', function ($user, $boardId) {
    return $user->tenant->boards()->where('id', $boardId)->exists();
});

Broadcast::channel('tasks.{taskId}', function ($user, $taskId) {
    return Task::find($taskId)->belongsToTenant($user->tenant);
});

