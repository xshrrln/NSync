<?php
use Livewire\Volt\Component;
use App\Models\Board;

new class extends Component {
    public $name = '';
    public $editingBoardId = null;
    public $editingBoardName = '';

    public function with() {
        $tenant = app('currentTenant');
        return [
            'boards' => $tenant ? Board::where('tenant_id', $tenant->id)->latest()->get() : collect(),
        ];
    }

    public function createBoard() {
        $this->validate(['name' => 'required|min:3|max:100']);
        $tenant = app('currentTenant');
        Board::create([
            'tenant_id' => $tenant->id,
            'name' => $this->name,
            'slug' => str($this->name)->slug() . '-' . uniqid(),
        ]);
        $this->name = '';
    }

    public function editBoard($boardId) {
        $tenant = app('currentTenant');
        $board = Board::where('id', $boardId)->where('tenant_id', $tenant->id)->firstOrFail();
        $this->editingBoardId = $boardId;
        $this->editingBoardName = $board->name;
    }

    public function updateBoard() {
        $this->validate(['editingBoardName' => 'required|min:3|max:100']);
        $board = Board::where('id', $this->editingBoardId)->where('user_id', auth()->id())->firstOrFail();
        $board->update(['name' => $this->editingBoardName]);
        $this->editingBoardId = null;
    }

    public function deleteBoard($boardId) {
        Board::where('id', $boardId)->where('user_id', auth()->id())->delete();
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
        <!-- Header -->
        <div class="bg-white bg-opacity-80 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-6 py-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Your Boards</h1>
                        <p class="text-gray-600 mt-1">Manage all your projects in one place</p>
                    </div>
                </div>
                
                <!-- Create Board Section -->
                <div x-data="{ creating: false }" class="flex gap-3">
                    <input type="text" wire:model="name" wire:keydown.enter="createBoard" 
                           placeholder="Board name..." 
                           class="flex-1 max-w-md px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    <button wire:click="createBoard" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                        Create Board
                    </button>
                </div>
            </div>
        </div>

        <!-- Boards Grid -->
        <div class="max-w-7xl mx-auto px-6 py-12">
            @if($boards->count() > 0)
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 auto-rows-max">
                    @foreach($boards as $board)
                        <div class="group" wire:key="board-{{ $board->id }}">
                            @if($editingBoardId === $board->id)
                                <!-- Edit Mode -->
                                <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 space-y-4">
                                    <input type="text" wire:model="editingBoardName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm font-bold">
                                    <div class="flex gap-2">
                                        <button wire:click="updateBoard" class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">Save</button>
                                        <button wire:click="$set('editingBoardId', null)" class="flex-1 px-4 py-2 bg-gray-200 text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-300 transition">Cancel</button>
                                    </div>
                                </div>
                            @else
                                <!-- Board Card -->
                                <a href="{{ route('boards.show', $board->slug) }}" class="block">
                                    <div class="relative h-24 bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-md hover:shadow-lg transition-all cursor-pointer overflow-hidden group">
                                        <!-- Decorative Background -->
                                        <div class="absolute inset-0 opacity-10">
                                            <div class="absolute top-2 right-2 w-12 h-12 bg-white rounded-full"></div>
                                            <div class="absolute bottom-4 left-4 w-8 h-8 bg-white rounded-full"></div>
                                        </div>
                                        
                                        <!-- Content -->
                                        <div class="relative h-full flex flex-col justify-between p-4 text-white">
                                            <h3 class="font-bold text-lg leading-tight line-clamp-2">{{ $board->name }}</h3>
                                            <p class="text-xs text-white text-opacity-80">Open board</p>
                                        </div>
                                    </div>
                                </a>

                                <!-- Actions -->
                                <div class="mt-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button wire:click="editBoard({{ $board->id }})" class="flex-1 px-3 py-2 text-gray-700 bg-white border border-gray-300 text-xs font-medium rounded-lg hover:bg-gray-50 transition">Edit</button>
                                    <button wire:click="deleteBoard({{ $board->id }})" wire:confirm="Delete this board?" class="px-3 py-2 text-red-600 bg-white border border-gray-300 text-xs font-medium rounded-lg hover:bg-red-50 transition">Delete</button>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <!-- Add New Board Card -->
                    <div class="group" x-data="{ creating: false }">
                        <div x-show="!creating" @click="creating = true" class="h-24 bg-gray-200 rounded-lg shadow-md hover:shadow-lg hover:bg-gray-300 transition-all cursor-pointer flex items-center justify-center">
                            <div class="text-center">
                                <svg class="w-8 h-8 text-gray-500 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                                <p class="text-sm font-medium text-gray-600">Create</p>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <!-- Empty State -->
                <div class="flex items-center justify-center min-h-[60vh]">
                    <div class="text-center max-w-md">
                        <div class="w-32 h-32 bg-gradient-to-br from-gray-200 to-gray-300 rounded-2xl flex items-center justify-center mx-auto mb-6">
                            <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">No boards yet</h3>
                        <p class="text-gray-600 mb-6">Create your first board to get started with your projects</p>
                    </div>
                </div>
            @endif
        </div>
</div>
</div>