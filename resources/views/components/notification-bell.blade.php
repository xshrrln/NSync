@php
    $notificationCount = is_countable($notifications ?? null) ? count($notifications) : 0;
    $notificationKeys = collect($notifications ?? [])->map(function ($notification, $index) {
        $explicit = data_get($notification, 'key');
        if (filled($explicit)) {
            return (string) $explicit;
        }

        $type = (string) data_get($notification, 'type', 'notification');
        $title = (string) data_get($notification, 'title', 'Notification');
        $message = (string) data_get($notification, 'message', '');
        $createdAt = (string) data_get($notification, 'created_at', '');

        return $type . '-' . md5($title . '|' . $message . '|' . $createdAt . '|' . $index);
    })->values()->all();
@endphp

<div
    class="relative"
    x-data="(typeof notificationBell === 'function'
        ? notificationBell(@js($notificationKeys))
        : {
            open: false,
            keys: @js($notificationKeys),
            dismissed: [],
            init() {
                try {
                    const raw = window.localStorage.getItem('nsync.notifications.dismissed') || '[]';
                    const parsed = JSON.parse(raw);
                    this.dismissed = Array.isArray(parsed) ? parsed.map((key) => String(key)) : [];
                } catch (error) {
                    this.dismissed = [];
                }
            },
            isDismissed(key) {
                return this.dismissed.includes(String(key));
            },
            dismiss(key) {
                const normalized = String(key);
                if (this.isDismissed(normalized)) {
                    return;
                }

                this.dismissed = [...this.dismissed, normalized];
                this.persist();
            },
            markAllRead() {
                this.dismissed = [...new Set([...this.dismissed, ...this.keys.map((key) => String(key))])];
                this.persist();
            },
            persist() {
                try {
                    window.localStorage.setItem('nsync.notifications.dismissed', JSON.stringify(this.dismissed));
                } catch (error) {
                    // Ignore storage errors.
                }
            },
            get unreadCount() {
                return this.keys.filter((key) => !this.dismissed.includes(String(key))).length;
            }
        })"
    x-init="init(); open = false"
    @keydown.escape.window="open = false"
    @click.outside="open = false"
    @nsync-main-content-scrolled.window="open = false"
>
    <button
        type="button"
        class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition border-0 bg-transparent"
        @click.stop="open = !open"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        <span
            x-cloak
            x-show="unreadCount > 0"
            class="absolute -top-1 -right-1 block h-5 w-5 rounded-full ring-2 ring-white bg-red-500 text-xs text-white flex items-center justify-center font-bold"
        >
            <span x-text="unreadCount">{{ $notificationCount }}</span>
        </span>
    </button>

    <div
        x-show="open"
        x-cloak
        @click.stop
        x-transition.origin.top.right
        class="absolute right-0 top-full mt-2 z-50 p-0 shadow-2xl border border-gray-200 overflow-hidden bg-white"
        style="display: none; min-width: 24rem; border-radius: 1rem;"
        role="menu"
    >
        <div class="p-4 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
            <h4 class="font-semibold text-gray-900 text-lg mb-0">Notifications</h4>
            <button
                type="button"
                class="text-xs font-semibold text-nsync-green-700 hover:text-nsync-green-800 disabled:opacity-50"
                :disabled="unreadCount === 0"
                @click="markAllRead(); open = false"
            >
                Mark all as read
            </button>
        </div>

        <div class="max-h-96 overflow-y-auto">
            @forelse($notifications as $index => $notification)
                @php
                    $isRead = data_get($notification, 'read_at') !== null;
                    $type = data_get($notification, 'type');
                    $notificationKey = $notificationKeys[$index] ?? ('notification-' . $index);
                @endphp
                <div
                    class="p-4 border-b border-gray-50 hover:bg-gray-50 transition {{ $isRead ? 'bg-gray-50' : '' }}"
                    x-show="!isDismissed(@js($notificationKey))"
                    x-cloak
                    @click="dismiss(@js($notificationKey))"
                >
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            @if($type === 'task-assigned')
                                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                </div>
                            @elseif($type === 'release-available')
                                <div class="w-10 h-10 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"/>
                                    </svg>
                                </div>
                            @elseif($type === 'support-ticket')
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-amber-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4v-4z"/>
                                    </svg>
                                </div>
                            @else
                                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-900">{{ data_get($notification, 'title', 'Notification') }}</p>
                            <p class="text-sm text-gray-600 mt-1">{{ data_get($notification, 'message', '') }}</p>
                            <p class="text-xs text-gray-500 mt-1">
                                @if(data_get($notification, 'created_at'))
                                    {{ data_get($notification, 'created_at')->diffForHumans() }}
                                @else
                                    just now
                                @endif
                            </p>
                            @if(data_get($notification, 'url'))
                                <a
                                    href="{{ data_get($notification, 'url') }}"
                                    class="mt-2 inline-flex text-xs font-semibold text-nsync-green-700 hover:text-nsync-green-800"
                                    @click.stop="dismiss(@js($notificationKey))"
                                >
                                    {{ data_get($notification, 'action_label', 'Open') }}
                                </a>
                            @endif
                        </div>
                        <button
                            type="button"
                            class="text-gray-400 hover:text-gray-600"
                            @click.stop="dismiss(@js($notificationKey))"
                            aria-label="Dismiss notification"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <p class="text-sm font-medium mb-0">No notifications</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
