<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Task;
use App\Models\Stage;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $newBoardName = '';

    public function with() {
        $tenant = app('currentTenant');
        return [
            'boards' => $tenant ? Board::where('tenant_id', $tenant->id)->latest()->get() : collect(),
            'totalTasks' => $tenant ? Task::where('tenant_id', $tenant->id)->count() : 0,
            'totalStages' => $tenant ? Stage::where('tenant_id', $tenant->id)->count() : 0,
            'tenant' => $tenant,
        ];
    }

    public function createBoard() {
        $this->validate(['newBoardName' => 'required|min:3|max:50']);

        $tenant = app('currentTenant');
        Board::create([
            'tenant_id' => $tenant->id,
            'name' => $this->newBoardName,
            'slug' => str($this->newBoardName)->slug() . '-' . rand(100, 999),
        ]);

        $this->newBoardName = '';
        $this->dispatch('notify', 'Board created successfully!');
    }
}; ?>

<div class="py-5 bg-gray-50 min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center py-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 mb-0">Dashboard</h1>
                    <p class="text-gray-600 mb-0">Welcome back, {{ Auth::user()->name }}!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Boards -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Boards</h6>
                        <div class="bg-blue-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-blue-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $boards->count() }}</h2>
                    <p class="text-gray-600 text-sm mb-0 flex items-center gap-1">
                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                        Active projects
                    </p>
                </div>
            </div>

            <!-- Total Tasks -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Tasks</h6>
                        <div class="bg-green-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-green-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ number_format($totalTasks) }}</h2>
                    <p class="text-gray-600 text-sm mb-0">Total tasks across all boards</p>
                </div>
            </div>

            <!-- Total Stages -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Stages</h6>
                        <div class="bg-yellow-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-yellow-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ number_format($totalStages) }}</h2>
                    <p class="text-gray-600 text-sm mb-0">Workflow stages configured</p>
                </div>
            </div>

            <!-- System Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">System Status</h6>
                        <div class="bg-purple-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-purple-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $tenant->plan ?? 'Free' }} Plan</h2>
                    <p class="text-gray-600 text-sm mb-0">Your subscription tier</p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6">
                    <h5 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <form wire:submit="createBoard" class="flex gap-3">
                            <input type="text" wire:model="newBoardName" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm" placeholder="Enter board name..." required>
                            <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                </svg>
                                Create Board
                            </button>
                        </form>
                        <div class="flex items-center">
                            <a href="{{ route('boards.index') }}" class="px-6 py-2 bg-gray-200 text-gray-900 font-medium rounded-lg hover:bg-gray-300 transition shadow-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Manage Boards
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Boards Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Your Boards</h2>
                    <p class="text-gray-600 mb-0">Quick access to your recent projects</p>
                </div>
                <a href="{{ route('boards.index') }}" class="px-4 py-2 bg-blue-50 text-blue-700 font-medium rounded-lg hover:bg-blue-100 transition flex items-center gap-2">
                    View All
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        @if($boards->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($boards->take(8) as $board)
                    <a href="{{ route('boards.show', $board->slug) }}" class="text-decoration-none">
                        <div class="relative h-32 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-sm hover:shadow-lg transition-all cursor-pointer overflow-hidden">
                            <div class="absolute inset-0 flex flex-col justify-between p-6 text-white">
                                <h6 class="font-bold text-lg leading-tight truncate">{{ $board->name }}</h6>
                                <small class="opacity-90">Open board</small>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full">
                        <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg text-center py-12">
                            <div class="mb-4">
                                <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full">
                                    <svg class="text-blue-600 w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-3">No boards yet</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                            <a href="{{ route('boards.index') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                                Create Your First Board
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg text-center py-12">
                <div class="mb-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-100 rounded-full">
                        <svg class="text-blue-600 w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-3">No boards yet</h3>
                <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                <a href="{{ route('boards.index') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                    Create Your First Board
                </a>
            </div>
        @endif
    </div>
</div>
