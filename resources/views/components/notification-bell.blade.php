<div class="relative" x-data="{ open: false, notifications: @json($notifications) }">
    <button @click="open = !open" class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span x-show="notifications.length > 0" class="absolute -top-1 -right-1 block h-5 w-5 rounded-full ring-2 ring-white bg-red-400 text-xs text-white flex items-center justify-center font-bold" x-text="notifications.length"></span>
    </button>
    
    <div x-show="open" x-transition @click.away="open = false" class="absolute right-0 mt-2 w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 overflow-hidden">
        <div class="p-4 border-b border-gray-100 bg-gray-50">
            <h4 class="font-semibold text-gray-900 text-lg">Notifications</h4>
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $notification)
                <div class="p-4 border-b border-gray-50 hover:bg-gray-50 transition @if($notification->read_at) bg-gray-50 @endif">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            @if($notification->type === 'task-assigned')
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ $notification->title }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ $notification->message }}</p>
                            <p class="text-xs text-gray-500 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-sm font-medium">No notifications</p>
                </div>
            @endforelse
        </div>
        <div class="p-3 border-t border-gray-100 bg-gray-50">
            <button class="w-full text-sm text-gray-700 font-medium hover:text-gray-900 transition">View all</button>
        </div>
    </div>
</div>
