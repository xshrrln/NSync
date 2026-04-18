<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Task;

new class extends Component {
    public $board;
    public $newTaskTitle = '';

    public function mount($slug) {
        $this->board = Board::where('slug', $slug)->where('user_id', auth()->id())->firstOrFail();
    }

    public function with() {
        return [
            'todoTasks' => Task::where('board_id', $this->board->id)->where('status', 'todo')->orderBy('position')->get(),
            'progressTasks' => Task::where('board_id', $this->board->id)->where('status', 'progress')->orderBy('position')->get(),
            'doneTasks' => Task::where('board_id', $this->board->id)->where('status', 'done')->orderBy('position')->get(),
        ];
    }

    public function addTask($status) {
        $this->validate(['newTaskTitle' => 'required|min:2']);
        Task::create([
            'board_id' => $this->board->id,
            'user_id' => auth()->id(),
            'title' => $this->newTaskTitle,
            'status' => $status,
            'position' => Task::where('board_id', $this->board->id)->where('status', $status)->count(),
        ]);
        $this->newTaskTitle = '';
    }

    public function updateTaskStatus($taskId, $newStatus) {
        $task = Task::where('user_id', auth()->id())->where('board_id', $this->board->id)->findOrFail($taskId);
        $task->update(['status' => $newStatus]);
    }

    public function deleteTask($taskId) {
        Task::where('user_id', auth()->id())->where('id', $taskId)->delete();
    }
}; ?>

<div class="py-5 bg-white min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center gap-4 py-4">
                <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-medium">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Workspace
                </a>
                <h1 class="text-2xl font-bold text-gray-900 mb-0">{{ $board->name }}</h1>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach(['todo' => 'To Do', 'progress' => 'In Progress', 'done' => 'Done'] as $status => $label)
                @php $tasks = ${$status . 'Tasks'}; @endphp
                <div class="bg-white/50 backdrop-blur-sm rounded-xl p-6 min-h-[70vh] shadow-sm border border-white/50 hover:shadow-md transition-all">
                    <div class="flex justify-between items-center mb-6">
                        <h6 class="text-sm font-bold uppercase tracking-wider text-gray-500">{{ $label }}</h6>
                        <span class="px-3 py-1 bg-white text-gray-900 text-xs font-bold rounded-full shadow-sm border">{{ $tasks->count() }}</span>
                    </div>

                    <div class="space-y-4 flex-1">
                        @forelse($tasks as $task)
                            <div class="bg-white p-4 border border-gray-200 shadow-sm rounded-xl hover:shadow-md transition-all">
                                <div class="flex justify-between items-start gap-3 mb-3">
                                    <p class="font-semibold text-gray-900 text-sm leading-relaxed flex-1 min-w-0 truncate">{{ $task->title }}</p>
                                    <button wire:click="deleteTask({{ $task->id }})" class="p-1 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition flex-shrink-0">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </div>
                                <div class="flex gap-2">
                                    @if($status !== 'todo')
                                        <button wire:click="updateTaskStatus({{ $task->id }}, 'todo')" class="flex-1 px-3 py-1.5 text-xs bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition text-left">← Back</button>
                                    @endif
                                    @if($status !== 'done')
                                        <button wire:click="updateTaskStatus({{ $task->id }}, '{{ $status === 'todo' ? 'progress' : 'done' }}')" class="flex-1 px-3 py-1.5 text-xs bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">→ Move</button>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="flex items-center justify-center h-32 border-2 border-dashed border-gray-300 rounded-xl bg-gray-50">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wider">No tasks</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <div class="flex gap-2">
                            <input type="text" wire:model="newTaskTitle" wire:keydown.enter="addTask('{{ $status }}')" class="flex-1 px-4 py-2.5 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Add new task...">
                            <button wire:click="addTask('{{ $status }}')" class="px-4 py-2.5 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition shadow-sm">+</button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>




