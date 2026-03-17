<?php
use Livewire\Volt\Component;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    // 1. Fetch data specifically for the current tenant context
    public function with() {
        return [
            'stages' => Stage::orderBy('order')
                ->with(['tasks' => fn($q) => $q->orderBy('position')])
                ->get(),
        ];
    }

    // 2. Kanban Movement Logic
    public function moveTask($taskId, $newStageId) {
        $task = Task::find($taskId);
        
        if ($task) {
            $task->update(['stage_id' => $newStageId]);
            
            // Log activity for accountability (Pro Plan Feature)
            activity()
                ->performedOn($task)
                ->causedBy(auth()->user())
                ->log("moved task to " . Stage::find($newStageId)->name);
        }
    }

    // 3. Task Creation Logic
    public function addTask($stageId, $title) {
        if (empty($title)) return;

        // Plan Limit Check (Free Plan: Max 50 tasks)
        $tenant = \App\Models\Tenant::current();
        if ($tenant->plan === 'free' && Task::count() >= 50) {
            $this->dispatch('notify', message: 'Task limit reached. Upgrade to Standard.');
            return;
        }

        Task::create([
            'title' => $title,
            'stage_id' => $stageId,
            'user_id' => auth()->id(),
            'position' => Task::where('stage_id', $stageId)->count() + 1
        ]);
    }

    // 4. Role-Restricted Deletion (Supervisor/Admin Only)
    public function deleteTask($taskId) {
        $task = Task::find($taskId);
        
        if ($task && auth()->user()->hasAnyRole(['Team Supervisor', 'Platform Administrator'])) {
            $task->delete();
        }
    }
}; ?>

<div class="flex space-x-6 overflow-x-auto pb-8 p-4 bg-slate-900 min-h-screen font-sans" x-data>
    @foreach($stages as $stage)
        <div class="flex-shrink-0 w-85 bg-slate-800/40 backdrop-blur-xl rounded-2xl p-5 border border-slate-700/50 shadow-2xl"
             x-on:dragover.prevent
             x-on:drop.prevent="$wire.moveTask($event.dataTransfer.getData('taskId'), {{ $stage->id }})">
            
            <div class="flex justify-between items-center mb-6">
                <h2 class="font-extrabold text-slate-400 uppercase text-xs tracking-[0.2em]">{{ $stage->name }}</h2>
                <span class="bg-slate-700 text-slate-300 text-[10px] px-2.5 py-1 rounded-lg font-mono">
                    {{ $stage->tasks->count() }}
                </span>
            </div>

            <div class="space-y-4 min-h-[300px]">
                @foreach($stage->tasks as $task)
                    <div class="group bg-slate-700/80 p-5 rounded-xl shadow-lg border border-slate-600/50 cursor-grab active:cursor-grabbing hover:border-blue-500/50 hover:bg-slate-700 transition-all relative"
                         draggable="true"
                         x-on:dragstart="$event.dataTransfer.setData('taskId', {{ $task->id }})">
                        
                        <div class="flex flex-col gap-2">
                            <p class="text-sm font-bold text-slate-100 leading-tight">{{ $task->title }}</p>
                            @if($task->description)
                                <p class="text-xs text-slate-400 line-clamp-2 italic">{{ $task->description }}</p>
                            @endif
                        </div>

                        <div class="mt-5 flex items-center justify-between border-t border-slate-600/30 pt-4">
                            <span class="text-[10px] text-slate-500 font-mono">NS-{{ $task->id }}</span>
                            
                            <div class="flex items-center gap-3">
                                @hasanyrole('Team Supervisor|Platform Administrator')
                                    <button wire:click="deleteTask({{ $task->id }})" 
                                            wire:confirm="Confirm deletion of NS-{{ $task->id }}?"
                                            class="opacity-0 group-hover:opacity-100 text-red-500 hover:text-red-400 transition-opacity p-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                @endhasanyrole
                                
                                <div class="w-6 h-6 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-500 flex items-center justify-center text-[10px] text-white font-black shadow-inner">
                                    {{ substr(auth()->user()->name, 0, 1) }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div x-data="{ open: false }" class="mt-6">
                <button x-show="!open" @click="open = true" class="group w-full py-3 flex items-center justify-center gap-2 rounded-xl border border-dashed border-slate-700 hover:border-blue-500/50 hover:bg-blue-500/5 transition-all text-slate-500 hover:text-blue-400">
                    <span class="text-lg leading-none">+</span>
                    <span class="text-xs font-black uppercase tracking-widest">New Task</span>
                </button>
                
                <div x-show="open" @click.away="open = false" x-transition class="bg-slate-900 p-2 rounded-xl border border-slate-700">
                    <input type="text" 
                           x-init="$el.focus()"
                           wire:keydown.enter="addTask({{ $stage->id }}, $event.target.value); open = false"
                           class="w-full bg-transparent border-none text-slate-200 text-sm focus:ring-0 placeholder-slate-600"
                           placeholder="What needs to be done?">
                </div>
            </div>
        </div>
    @endforeach

    @hasanyrole('Team Supervisor|Platform Administrator')
        <div class="flex-shrink-0 w-80 h-16 rounded-2xl border-2 border-dashed border-slate-800 flex items-center justify-center text-slate-700 hover:border-slate-700 hover:text-slate-500 cursor-pointer transition-all">
            <span class="text-xs font-black uppercase tracking-widest">+ Add Section</span>
        </div>
    @endhasanyrole
</div>