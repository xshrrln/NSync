<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $boardSlug;
    public $board;
    public $newTaskTitle = '';
    public $newTaskStageId = null;
    
    public function mount($boardSlug) {
        $this->boardSlug = $boardSlug;
        $tenant = app('currentTenant');
        $this->board = Board::where('slug', $boardSlug)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
    }

    public function with() {
        return [
            'stages' => Stage::where('board_id', $this->board->id)
                ->orderBy('position')
                ->with(['tasks' => fn($q) => $q->orderBy('position')])
                ->get(),
        ];
    }

    public function moveTask($taskId, $newStageId) {
            $tenant = app('currentTenant');
            $task = Task::where('id', $taskId)
            ->where('board_id', $this->board->id)
            ->where('tenant_id', $tenant->id)
            ->first();
            
        if ($task) {
            $task->update([
                'stage_id' => $newStageId,
                'position' => Task::where('stage_id', $newStageId)->max('position') + 1
            ]);
        }
    }

    public function addTask($stageId) {
        if (empty(trim($this->newTaskTitle))) {
            return;
        }
        
        Task::create([
            'title' => trim($this->newTaskTitle),
            'stage_id' => $stageId,
            'board_id' => $this->board->id,
            'tenant_id' => app('currentTenant')->id,
            'position' => Task::where('stage_id', $stageId)->count() + 1
        ]);
        
        $this->newTaskTitle = '';
        $this->newTaskStageId = null;
    }

    public function deleteTask($taskId) {
        Task::where('id', $taskId)
            ->where('board_id', $this->board->id)
            ->delete();
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
        <!-- Header -->
        <div class="bg-white bg-opacity-80 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-full mx-auto px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('boards.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 transition">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        <span class="text-sm font-medium">Back</span>
                    </a>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $board->name }}</h1>
                </div>
            </div>
        </div>

        @if($stages->isEmpty())
            <div class="flex items-center justify-center min-h-[70vh] p-8">
                <div class="max-w-md text-center">
                    <div class="w-32 h-32 bg-gradient-to-br from-gray-200 to-gray-300 rounded-2xl flex items-center justify-center mx-auto mb-6">
                        <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">No Stages Yet</h3>
                    <p class="text-gray-600">Create stages to organize your tasks</p>
                </div>
            </div>
        @else
            <!-- Kanban Board -->
            <div class="flex overflow-x-auto pb-12 px-6 py-8 gap-6">
                @foreach($stages as $stage)
                    <div class="flex-shrink-0 w-[360px]" x-data="{ addingTask: false }">
                        <!-- Column Header -->
                        <div class="mb-4 flex items-center justify-between px-4">
                            <div class="flex items-center gap-3">
                                <h2 class="font-bold text-gray-900 text-base">{{ $stage->name }}</h2>
                                <span class="bg-gray-300 text-gray-700 text-xs px-2 py-1 rounded-full font-semibold">{{ $stage->tasks->count() }}</span>
                            </div>
                        </div>

                        <!-- Tasks Container -->
                        <div class="space-y-3 min-h-[400px] p-3 bg-gray-200 bg-opacity-50 rounded-lg transition-all"
                             x-on:dragover.prevent="$el.classList.add('bg-opacity-100', 'bg-blue-100')"
                             x-on:dragleave="$el.classList.remove('bg-opacity-100', 'bg-blue-100')"
                             x-on:drop.prevent="$el.classList.remove('bg-opacity-100', 'bg-blue-100'); $wire.moveTask($event.dataTransfer.getData('taskId'), {{ $stage->id }})"
                             x-data>
                            
                            @foreach($stage->tasks as $task)
                                <div class="group bg-white p-4 rounded-lg shadow-sm border border-gray-200 cursor-move hover:shadow-md transition-shadow"
                                     draggable="true"
                                     x-on:dragstart="$event.dataTransfer.effectAllowed = 'move'; $event.dataTransfer.setData('taskId', {{ $task->id }})">
                                    <div class="flex justify-between items-start gap-2 mb-2">
                                        <p class="font-medium text-gray-900 text-sm flex-1 leading-snug">{{ $task->title }}</p>
                                        <button wire:click="deleteTask({{ $task->id }})" class="p-1 text-gray-400 hover:text-red-600 transition flex-shrink-0 opacity-0 group-hover:opacity-100">
                                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 1a.5.5 0 0 0-.5.5v1h3V1.5a.5.5 0 0 0-.5-.5h-2z"/>
                                            </svg>
                                        </button>
                                    </div>
                                    @if($task->description)
                                        <p class="text-xs text-gray-600 line-clamp-2">{{ $task->description }}</p>
                                    @endif
                                </div>
                            @endforeach

                            <!-- Add Task -->
                            <div x-data="{ open: false }" class="mt-2">
                                <button x-show="!open" @click="open = true; $nextTick(() => $refs.taskInput?.focus())" 
                                        class="w-full text-left px-4 py-3 text-gray-600 hover:text-gray-900 hover:bg-black hover:bg-opacity-5 rounded-lg transition text-sm font-medium">
                                    + Add a card
                                </button>
                                
                                <div x-show="open" @click.away="open = false" x-transition class="space-y-2">
                                    <input type="text" 
                                           wire:model="newTaskTitle"
                                           x-ref="taskInput"
                                           @keydown.enter="$wire.addTask({{ $stage->id }}); open = false"
                                           class="w-full border border-gray-300 text-gray-900 text-sm rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent transition outline-none"
                                           placeholder="Enter card title...">
                                    <div class="flex gap-2">
                                        <button wire:click="addTask({{ $stage->id }})" @click="open = false" class="flex-1 px-3 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">Add</button>
                                        <button @click="open = false" class="flex-1 px-3 py-2 text-sm font-medium text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
</div>

