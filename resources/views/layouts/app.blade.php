<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ config('app.name', 'Laravel') }} - @yield('title', 'Workspace')</title>
    <link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 256 256'><path fill='%23f55246' d='M52 45 17 73l79 61-44 34 123 95-53-92 77-60Z'/></svg>">
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles</head>
@php
    $authUser = auth()->user();
    $currentTenant = app()->bound('currentTenant') ? app('currentTenant') : null;
    $theme = is_array($currentTenant?->theme) ? $currentTenant->theme : [];
    $defaultTheme = ['primary' => '#16A34A', 'secondary' => '#FFFFFF'];
    $tenantTheme = [
        'primary' => $theme['primary'] ?? $defaultTheme['primary'],
        'secondary' => $theme['secondary'] ?? $defaultTheme['secondary'],
    ];
@endphp
<body
    class="bg-white"
    style="--tenant-primary: {{ $tenantTheme['primary'] }}; --tenant-secondary: {{ $tenantTheme['secondary'] }};"
>
    @php
        $notifications = collect();
        $isTenantAdminUser = $authUser
            && $currentTenant
            && strcasecmp((string) $authUser->email, (string) ($currentTenant->tenant_admin_email ?? '')) === 0;

        if ($isTenantAdminUser && ! $authUser->hasRole('Platform Administrator') && class_exists(\App\Models\Patch::class)) {
            $appliedPatchIds = collect($currentTenant->patches_applied ?? [])->map(fn ($id) => (int) $id)->all();

            $notifications = \App\Models\Patch::query()
                ->whereNotIn('id', $appliedPatchIds)
                ->latest()
                ->take(5)
                ->get()
                ->map(fn ($patch) => [
                    'id' => $patch->id,
                    'key' => 'patch-' . $patch->id,
                    'type' => 'patch-available',
                    'title' => 'New patch available',
                    'message' => $patch->title . ' is ready for your workspace. Review it in Update Center and choose when to apply it.',
                    'created_at' => $patch->created_at,
                    'url' => route('update-center'),
                    'action_label' => 'Open Update Center',
                ]);
        }
    @endphp

    @if(!$authUser)
        {{ $slot }}
        @livewireScripts
        @stack('scripts')
    @else
    <div
        class="h-screen overflow-hidden flex flex-col"
        x-data="{
            subscriptionModalOpen: false,
            subscriptionModalTitle: 'Subscription Required',
            subscriptionModalMessage: '',
            theme: {
                primary: @js($tenantTheme['primary']),
                secondary: @js($tenantTheme['secondary'])
            },
            get themeStyle() {
                return `--tenant-primary: ${this.theme.primary}; --tenant-secondary: ${this.theme.secondary};`;
            },
            openSubscriptionModal(detail = {}) {
                this.subscriptionModalTitle = detail.title || 'Subscription Required';
                this.subscriptionModalMessage = detail.message || 'Your free trial has ended. Please avail a subscription to access your workspace data and continue using NSync.';
                this.subscriptionModalOpen = true;
            },
            applyTheme(detail = {}) {
                if (! detail.primary || ! detail.secondary) {
                    return;
                }

                this.theme = {
                    primary: detail.primary,
                    secondary: detail.secondary,
                };
            }
        }"
        x-on:subscription-expired.window="openSubscriptionModal($event.detail || {})"
        x-on:tenant-theme-updated.window="applyTheme($event.detail || {})"
        x-bind:style="themeStyle"
    >
        <!-- Global Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="w-full px-3 sm:px-4 py-4">
                <div class="flex items-center justify-between">
                    <!-- Left: Logo + Workspace Switcher + Search -->
                    <div class="flex items-center gap-4">
                        <!-- Logo -->
<a href="{{ auth()->user()->hasRole('Platform Administrator') ? route('admin.dashboard') : route('dashboard') }}" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-xl transition">
                            <div class="text-2xl font-black tracking-tight select-none">
                                N<span class="text-green-600">S</span>ync
                            </div>
                        </a>
                        
                        <!-- Search Bar -->
                        <div class="w-72 sm:w-80 lg:w-96">
                            <x-search-bar />
                        </div>
                    </div>
                    
                    <!-- Right: Actions -->
                    <div class="flex items-center gap-3">
                        <!-- Notification Bell -->
                        <x-notification-bell :notifications="$notifications" />
                        
                        <!-- User Profile -->
                        <x-dropdown align="right">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100 rounded-xl transition font-medium text-sm">
                                    <div class="w-9 h-9 bg-gradient-to-br from-gray-600 to-gray-700 rounded-2xl flex items-center justify-center text-white font-semibold text-sm shadow-lg">
                                        {{ strtoupper(substr($authUser->name, 0, 2)) }}
                                    </div>
                                    <span class="hidden md:block">{{ $authUser->name }}</span>
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="p-3 border-b">
                                    <p class="text-sm font-semibold">{{ $authUser->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $authUser->email }}</p>
                                </div>
                                <a href="{{ route('settings') }}" class="block px-4 py-2 text-sm hover:bg-gray-100 rounded-lg transition">Settings</a>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg transition">Log out</button>
                                </form>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main Content with Sidebar -->
        <div class="flex flex-1 min-h-0 overflow-hidden bg-[var(--tenant-secondary)] transition-colors duration-300">
            <!-- Sidebar Navigation -->
            <aside class="w-64 h-full overflow-y-auto bg-white border-r shadow-sm flex-shrink-0">
                <div class="px-6 pt-4 pb-10 border-b">
                    <div class="text-sm font-semibold text-gray-700 uppercase tracking-wide">Workspace</div>
                    <div class="flex items-center gap-2 mt-2">
                        <span class="text-base font-bold text-gray-900">{{ $currentTenant->name ?? 'No workspace selected' }}</span>
                        @if($currentTenant)
                            <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ ucfirst($currentTenant->plan) }} Plan</span>
                        @endif
                    </div>
                </div>
                <nav class="p-2">
                        <a href="{{ auth()->user()->hasRole('Platform Administrator') ? route('admin.dashboard') : route('dashboard') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('dashboard') || request()->routeIs('admin.dashboard') ? 'bg-nsync-green-50 border-r-2 border-nsync-green-600 text-nsync-green-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                            <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Dashboard
                        </a>
                    <a href="{{ route('boards.index') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('boards.*') ? 'bg-nsync-green-50 border-r-2 border-nsync-green-600 text-nsync-green-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Boards
                    </a>
                    <a href="{{ route('team-members') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('team-members') ? 'bg-nsync-green-50 border-r-2 border-nsync-green-600 text-nsync-green-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.128 0M7 13v-1a4 4 0 013.268-3.137 3.998 3.998 0 016.463-.232l.342.574a3.998 3.998 0 006.463.232 4 4 0 013.268 3.137V13M17.29 13.47a12.001 12.001 0 01-1.8 0M12 13a12 12 0 01-1.8 0"/>
                        </svg>
                        Team Members
                    </a>
                    <a href="{{ route('billing') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('billing') ? 'bg-nsync-green-50 border-r-2 border-nsync-green-600 text-nsync-green-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 0V4m0 2a6 6 0 00-6 6m6-6a6 6 0 016 6m-6 6v-2m0 0v-2m0 4a6 6 0 01-6-6m6 6a6 6 0 006-6"/>
                        </svg>
                        Billing
                    </a>
                    <a href="{{ route('reports') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('reports') ? 'bg-nsync-green-50 border-r-2 border-nsync-green-600 text-nsync-green-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Reports
                    </a>
                    <a href="{{ route('settings') }}" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 3.35 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-3.35 1.756 0 3.35a1.724 1.724 0 002.573 1.066c1.543.94 3.31-.826 2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-3.35-.426 0-3.35a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826 2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756.426-3.35.426 0 3.35a1.724 1.724 0 00-2.573 1.066c-1.543-.94-3.31.826-2.37 2.37a1.724 1.724 0 00-1.065 2.572c.426 1.756 3.35.426 0 3.35a1.724 1.724 0 002.573-1.066c1.543-.94 3.31.826 2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-3.35-.426 0-3.35a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826 2.37-2.37a1.724 1.724 0 00-1.065-2.572c1.756.426 3.35.426 0 3.35a1.724 1.724 0 002.573 1.066c1.543 .94 3.31 -.826 2.37 -2.37a1.724 1.724 0 00-1.065 -2.572c-1.756 -.426 -3.35 .426 0 3.35a1.724 1.724 0 00-2.573 -1.066c-1.543 .94 -3.31 -.826 2.37 -2.37a1.724 1.724 0 00-1.065 -2.572c.426 1.756 3.35 .426 0 3.35a1.724 1.724 0 002.573 1.066c1.543 .94 3.31 -.826 2.37 -2.37"/>
                        </svg>
                        Settings
                    </a>
                </nav>
            </aside>
            
            <!-- Page Content -->
            <main class="flex-1 min-h-0 overflow-y-auto px-8 pb-8 pt-0 bg-[var(--tenant-secondary)] transition-colors duration-300">
                {{ $slot }}
            </main>
        </div>

        <div
            x-show="subscriptionModalOpen"
            x-cloak
            class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm"
        >
            <div
                @click.away="subscriptionModalOpen = false"
                class="w-full max-w-lg overflow-hidden rounded-3xl border border-amber-100 bg-white shadow-2xl"
            >
                <div class="border-b border-amber-100 bg-amber-50 px-6 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-amber-600">Workspace Access Paused</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-900" x-text="subscriptionModalTitle"></h2>
                        </div>
                        <button @click="subscriptionModalOpen = false" class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-slate-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <p class="text-base leading-7 text-slate-600" x-text="subscriptionModalMessage"></p>
                    <p class="mt-4 text-sm text-slate-500">
                        Subscribe or update billing to keep working with your boards, members, and saved data.
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-5">
                    <button @click="subscriptionModalOpen = false" class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200">
                        Maybe Later
                    </button>
                    <a href="{{ route('billing') }}" class="rounded-xl bg-nsync-green-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-nsync-green-700">
                        Go to Billing
                    </a>
                </div>
            </div>
        </div>

    </div>
    @endif
    
    @livewireScripts
    @stack('scripts')
</body>
</html>
