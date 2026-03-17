<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'NSync') }} - @yield('title', 'Workspace')</title>
    
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div x-data>
        <!-- Global Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Left: Logo + Workspace Switcher + Search -->
                    <div class="flex items-center gap-4">
                        <!-- Logo -->
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-xl transition">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-xl flex items-center justify-center shadow-lg">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                                </svg>
                            </div>
                            <span class="text-xl font-bold text-gray-900 hidden lg:block">NSync</span>
                        </a>
                        
                        <!-- Workspace Switcher -->
                        <x-workspace-switcher :current-tenant="auth()->user()->tenant" :tenants="auth()->user()->tenants ?? []" />
                        
                        <!-- Search Bar -->
                        <x-search-bar />
                    </div>
                    
                    <!-- Right: Actions -->
                    <div class="flex items-center gap-3">
                        <!-- Notification Bell -->
                        <x-notification-bell :notifications="[]" />
                        
                        <!-- User Profile -->
                        <x-dropdown align="right">
                            <x-slot name="trigger">
                                <button class="flex items-center gap-3 px-4 py-2 hover:bg-gray-100 rounded-xl transition font-medium text-sm">
                                    <div class="w-9 h-9 bg-gradient-to-br from-gray-600 to-gray-700 rounded-2xl flex items-center justify-center text-white font-semibold text-sm shadow-lg">
                                        {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                                    </div>
                                    <span class="hidden md:block">{{ auth()->user()->name }}</span>
                                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <div class="p-3 border-b">
                                    <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
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
        <div class="flex">
            <!-- Sidebar Navigation -->
            <aside class="w-64 bg-white border-r shadow-sm flex-shrink-0">
                <div class="p-6 border-b">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Workspace</h3>
                    <p class="text-sm text-gray-600 mb-4">{{ app('currentTenant')->name ?? 'No workspace selected' }}</p>
                    @if(app('currentTenant'))
                        <span class="inline-flex px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">{{ ucfirst(app('currentTenant')->plan) }} Plan</span>
                    @endif
                </div>
                <nav class="p-2">
                    <a href="{{ route('dashboard') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('dashboard') ? 'bg-blue-50 border-r-2 border-blue-600 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Dashboard
                    </a>
                    <a href="{{ route('boards.index') }}" class="flex items-center px-4 py-3 {{ request()->routeIs('boards.*') ? 'bg-blue-50 border-r-2 border-blue-600 text-blue-700' : 'text-gray-700 hover:bg-gray-50' }} font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Boards
                    </a>
                    <a href="#" class="flex items-center px-4 py-3 text-gray-700 hover:bg-gray-50 font-medium rounded-r-lg transition group">
                        <svg class="w-5 h-5 mr-3 opacity-75 group-hover:opacity-100" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.128 0M7 13v-1a4 4 0 013.268-3.137 3.998 3.998 0 016.463-.232l.342.574a3.998 3.998 0 006.463.232 4 4 0 013.268 3.137V13M17.29 13.47a12.001 12.001 0 01-1.8 0M12 13a12 12 0 01-1.8 0"/>
                        </svg>
                        Team Members
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
            <main class="flex-1 min-h-screen">
                {{ $slot }}
            </main>
        </div>
    </div>
    
    @livewireScripts
    @stack('scripts')
</body>
</html>
