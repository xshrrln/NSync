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

<div id="board-detail-container">
    <div class="mb-5 d-flex justify-content-between align-items-center">
        <div>
            <nav aria-label="breadcrumb"><ol class="breadcrumb mb-1"><li class="breadcrumb-item"><a href="{{ route('dashboard') }}" class="text-decoration-none text-muted small">Workspace</a></li><li class="breadcrumb-item active small">{{ $board->name }}</li></ol></nav>
            <h1 class="h3 fw-bold text-dark tracking-tighter text-uppercase m-0">{{ $board->name }}</h1>
        </div>
    </div>

    <div class="row g-4">
        @foreach(['todo' => 'To Do', 'progress' => 'In Progress', 'done' => 'Done'] as $status => $label)
            @php $tasks = ${$status . 'Tasks'}; @endphp
            <div class="col-md-4">
                <div class="bg-light bg-opacity-50 rounded-4 p-3" style="min-height: 70vh;">
                    <div class="d-flex justify-content-between align-items-center mb-4 px-1">
                        <h6 class="fw-bold m-0 small text-uppercase tracking-widest text-muted">{{ $label }}</h6>
                        <span class="badge rounded-pill bg-white text-dark border px-2">{{ $tasks->count() }}</span>
                    </div>

                    <div class="mb-4">
                        @forelse($tasks as $task)
                            <div class="bg-white p-3 mb-3 border border-light shadow-sm rounded-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <p class="small fw-semibold text-dark m-0">{{ $task->title }}</p>
                                    <button wire:click="deleteTask({{ $task->id }})" class="btn p-0 text-danger small">×</button>
                                </div>
                                <div class="mt-3 d-flex gap-2">
                                    @if($status !== 'todo') <button wire:click="updateTaskStatus({{ $task->id }}, 'todo')" class="btn btn-link p-0 text-[9px] text-uppercase">Back</button> @endif
                                    @if($status !== 'done') <button wire:click="updateTaskStatus({{ $task->id }}, '{{ $status === 'todo' ? 'progress' : 'done' }}')" class="btn btn-link p-0 text-[9px] text-blue-600 text-uppercase">Move →</button> @endif
                                </div>
                            </div>
                        @empty
                            <div class="py-5 text-center border-2 border-dashed border-secondary border-opacity-10 rounded-4"><p class="text-[10px] text-muted uppercase fw-bold m-0">Empty</p></div>
                        @endforelse
                    </div>

                    <div class="mt-auto input-group">
                        <input type="text" wire:model="newTaskTitle" wire:keydown.enter="addTask('{{ $status }}')" class="form-control border-0 px-3" placeholder="New Task...">
                        <button wire:click="addTask('{{ $status }}')" class="btn btn-primary">+</button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>