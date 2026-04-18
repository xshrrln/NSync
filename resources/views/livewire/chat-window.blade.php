<?php
use Livewire\Volt\Component;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $messages = [];
    public $newMessage = '';

    public function with() {
        $tenant = app('currentTenant');
        return [
            'tenant' => $tenant,
        ];
    }

    public function sendMessage() {
        if (trim($this->newMessage)) {
            Message::create([
                'tenant_id' => app('currentTenant')->id,
                'user_id' => Auth::id(),
                'message' => trim($this->newMessage),
            ]);
            $this->newMessage = '';
            $this->dispatch('refresh-chat');
        }
    }

    public function mount() {
        $this->messages = Message::where('tenant_id', app('currentTenant')->id)
            ->with('user')
            ->latest()
            ->take(50)
            ->get()
            ->reverse();
    }
}; ?>

<div class="max-w-md mx-auto h-96 flex flex-col bg-white rounded-xl shadow-sm border border-gray-200 p-4">
    <!-- Chat Header -->
    <div class="p-4 border-b bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-t-xl">
        <h3 class="font-bold text-lg">Team Chat</h3>
        <p class="text-sm opacity-90">{{ $tenant->users->count() }} members online</p>
    </div>

    <!-- Messages -->
    <div class="flex-1 p-4 overflow-y-auto space-y-4">
        @forelse($messages as $message)
            <div class="flex {{ $message->user_id == Auth::id() ? 'justify-end' : 'justify-start' }}">
                <div class="{{ $message->user_id == Auth::id() ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-900' }} max-w-xs lg:max-w-md px-4 py-2 rounded-2xl">
                    <div class="text-sm">{{ $message->message }}</div>
                    <div class="text-xs opacity-75 mt-1">{{ $message->user->name }} • {{ $message->created_at->diffForHumans() }}</div>
                </div>
            </div>
        @empty
            <div class="text-center text-gray-500 py-8">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-sm">No messages yet. Start the conversation!</p>
            </div>
        @endforelse
    </div>

    <!-- Input -->
    <div class="p-4 border-t bg-gray-50">
        <div class="flex gap-2">
            <input wire:model="newMessage" wire:keydown.enter="sendMessage" type="text" placeholder="Type a message..." 
                   class="flex-1 px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent outline-none">
            <button wire:click="sendMessage" class="px-6 py-2 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-2-9-9 2 2 9z"/>
                </svg>
                Send
            </button>
        </div>
    </div>
</div>
