<?php

use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

new class extends Component {
    public array $adminNotifications = [];
    public int $unreadCount = 0;

    #[Livewire\Attributes\On('notification-received')]
    public function refreshNotifications(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        if (! Auth::check() || ! Schema::hasTable('notifications')) {
            $this->adminNotifications = [];
            $this->unreadCount = 0;
            return;
        }

        $notifications = Auth::user()
            ->unreadNotifications()
            ->latest()
            ->limit(12)
            ->get()
            ->map(function ($notification) {
                return array_merge($notification->data ?? [], [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ]);
            })
            ->toArray();

        $this->adminNotifications = $notifications;
        $this->unreadCount = count($notifications);
    }

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function markAsRead(string $notificationId): void
    {
        Auth::user()
            ->notifications()
            ->where('id', $notificationId)
            ->update(['read_at' => now()]);

        $this->loadNotifications();
    }

    public function markAllAsRead(): void
    {
        Auth::user()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

        $this->loadNotifications();
    }
} ?>

<div wire:poll-5s="loadNotifications">
    <button
        type="button"
        class="relative p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition border-0 bg-transparent"
        @click="$dispatch('notification-bell-toggle')"
        :aria-expanded="open ? 'true' : 'false'"
        aria-haspopup="menu"
    >
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
        </svg>
        @if($unreadCount > 0)
            <span class="absolute -top-1 -right-1 block h-5 w-5 rounded-full ring-2 ring-white bg-red-500 text-xs text-white flex items-center justify-center font-bold">
                {{ $unreadCount }}
            </span>
        @endif
    </button>
</div>
