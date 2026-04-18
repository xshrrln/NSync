<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <title>Admin - {{ config('app.name', 'NSync') }}</title>
    <link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 256 256'><path fill='%23f55246' d='M52 45 17 73l79 61-44 34 123 95-53-92 77-60Z'/></svg>">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    @livewireStyles

    <style>
        :root {
            --tenant-primary: #16a34a; 
            --tenant-primary-hover: #15803d;
        }
    </style>
</head>

<body class="bg-white text-gray-900 min-h-screen overflow-hidden">

<div class="flex h-screen overflow-hidden font-sans">
    <aside class="w-64 bg-white border-r border-gray-100 flex flex-col">
        <div class="p-8 border-b border-gray-50">
            <div class="flex flex-col">
                <h1 class="text-2xl font-black tracking-tighter leading-none text-gray-900 uppercase">
                    N<span style="color: var(--tenant-primary);">S</span>ync
                </h1>
            </div>
        </div>

        <nav class="flex-1 p-4 space-y-1 text-base overflow-y-auto">
            <a href="/dashboard"
               class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all
               {{ request()->routeIs('admin.dashboard') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-width="2" d="M3 12l2-2 7-7 7 7 2 2M5 10v10h14V10"/>
                </svg>
                Dashboard
            </a>

            <a href="{{ route('admin.tenants.index') }}"
               class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all
               {{ request()->routeIs('admin.tenants.*') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/>
                </svg>
                Tenants
            </a>

            <a href="{{ route('admin.billing') }}" class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all {{ request()->routeIs('admin.billing') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/></svg>
                Billing
            </a>

            <a href="{{ route('admin.archive') }}" class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all {{ request()->routeIs('admin.archive') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M4 7h16M6 7l1 12h10l1-12M9 7V4h6v3"/></svg>
                Archive
            </a>

            <a href="{{ route('admin.settings') }}" class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all {{ request()->routeIs('admin.settings') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                Settings
            </a>
        </nav>

        <div class="p-4 border-t border-gray-50">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 p-3 rounded-xl font-bold text-red-500 hover:bg-red-50 transition-all">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden">
        @php
            $hasSettingsTable = \Illuminate\Support\Facades\Schema::hasTable('app_settings');
            $appSettings = $hasSettingsTable ? \App\Models\AppSetting::data() : [];
            $showBanner = $appSettings['maintenance_enabled'] ?? false;
            $bannerMessage = $appSettings['maintenance_message'] ?? null;
        @endphp
        @if($showBanner && $bannerMessage)
            <div class="bg-amber-50 text-amber-900 border-b border-amber-100 px-8 py-3 text-sm font-semibold">
                {{ $bannerMessage }}
            </div>
        @endif

        <header class="bg-white border-b border-gray-50 px-8 py-[19.2px] flex justify-between items-center">
            <div class="relative w-72">
                <input type="text" placeholder="Quick search..." class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-gray-200 bg-gray-50/30 text-sm focus:bg-white focus:ring-2 outline-none transition-all" style="--tw-ring-color: var(--tenant-primary);">
                <svg class="w-4 h-4 absolute left-3 top-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            </div>

            @php
                $adminUser = auth()->user();
                $initials = collect(explode(' ', trim($adminUser->name ?? '')))->filter()->take(2)->map(fn($p) => strtoupper(mb_substr($p,0,1)))->implode('');
                $roleLabel = $adminUser?->getRoleNames()->first() ?? 'Platform Administrator';
            @endphp
            <div class="flex items-center gap-3 p-1.5 pr-4 hover:bg-gray-50 rounded-2xl cursor-pointer transition-all border border-transparent hover:border-gray-100">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-sm" style="background: var(--tenant-primary);">
                    {{ $initials ?: 'PA' }}
                </div>
                <div class="hidden md:block text-left">
                    <p class="text-sm font-bold text-gray-900 leading-none">{{ $adminUser?->name ?? 'Admin' }}</p>
                    <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mt-1">{{ $roleLabel }}</p>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto px-8 pb-8 pt-0 bg-white">
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts

</body>
</html>
