<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    
    <title>Admin - {{ config('app.name', 'NSync') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-logo.png') }}">

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
@php
    $systemFlashType = null;
    $systemFlashMessage = null;
    $adminNotifications = collect();

    foreach (['success', 'error', 'warning', 'message'] as $flashKey) {
        $flashValue = session()->pull($flashKey);

        if (filled(is_string($flashValue) ? trim($flashValue) : $flashValue)) {
            $systemFlashType = $flashKey === 'message' ? 'info' : $flashKey;
            $systemFlashMessage = trim((string) $flashValue);
            break;
        }
    }

    $adminNotifications = collect();
    $supportUnreadCount = 0;

    if (auth()->check() && \Illuminate\Support\Facades\Schema::hasTable('notifications')) {
        $allUnread = auth()->user()
            ->unreadNotifications()
            ->latest()
            ->limit(12)
            ->get();

        $supportUnreadCount = $allUnread->filter(
            fn ($notification) => in_array($notification->data['type'] ?? '', ['support-ticket', 'tenant-chat-message'], true)
        )->count();

        $adminNotifications = $allUnread
            ->reject(
                fn ($notification) => in_array($notification->data['type'] ?? '', ['support-ticket', 'tenant-chat-message'], true)
            )
            ->map(function ($notification) {
                return array_merge($notification->data ?? [], [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                ]);
            });
    }

    $systemFlashToastTone = match ($systemFlashType) {
        'success' => 'success',
        'warning' => 'warning',
        'info' => 'info',
        'error' => 'error',
        default => null,
    };
@endphp

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

            <a href="{{ route('admin.support.index') }}" class="flex items-center gap-3 p-3 rounded-xl font-bold transition-all {{ request()->routeIs('admin.support.*') ? 'bg-green-50 text-green-700' : 'text-gray-500 hover:bg-gray-50 hover:text-gray-900' }}">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4v-4z"/></svg>
                Support
                @if($supportUnreadCount > 0)
                    <span class="ml-auto inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">
                        {{ $supportUnreadCount }}
                    </span>
                @endif
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
            <div class="flex items-center gap-3">
                <x-notification-bell :notifications="$adminNotifications" />

                <a href="{{ route('admin.support.index') }}"
                   class="hidden rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50 md:inline-flex">
                    Open Support
                </a>

                <div class="flex items-center gap-3 p-1.5 pr-4 hover:bg-gray-50 rounded-2xl cursor-pointer transition-all border border-transparent hover:border-gray-100">
                    <div class="w-9 h-9 rounded-full flex items-center justify-center text-white font-bold text-xs shadow-sm" style="background: var(--tenant-primary);">
                        {{ $initials ?: 'PA' }}
                    </div>
                    <div class="hidden md:block text-left">
                        <p class="text-sm font-bold text-gray-900 leading-none">{{ $adminUser?->name ?? 'Admin' }}</p>
                        <p class="text-sm font-bold text-gray-400 uppercase tracking-wider mt-1">{{ $roleLabel }}</p>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto px-8 pb-8 pt-0 bg-white">
            @yield('content')
        </main>
    </div>
</div>

@livewireScripts
<div id="nsyncSystemToastRoot"
     class="pointer-events-auto"
     style="{{ filled($systemFlashMessage) ? 'display:block;' : 'display:none;' }} position:fixed; top:16px; right:16px; z-index:99999; width:min(92vw, 304px);"
     aria-live="polite"
     aria-atomic="true">
    <div id="nsyncSystemToastCard"
         class="pointer-events-auto overflow-hidden rounded-2xl border {{ $systemFlashToastTone === 'success' ? 'border-emerald-100' : ($systemFlashToastTone === 'warning' ? 'border-amber-100' : ($systemFlashToastTone === 'info' ? 'border-sky-100' : 'border-rose-100')) }} shadow-2xl ring-1 ring-black/5"
         style="background: rgba(255,255,255,0.98);">
        <div class="h-1 w-full bg-slate-100/90">
            <div id="nsyncSystemToastAccent" class="h-1 w-full origin-left {{ $systemFlashToastTone === 'success' ? 'bg-emerald-500' : ($systemFlashToastTone === 'warning' ? 'bg-amber-500' : ($systemFlashToastTone === 'info' ? 'bg-sky-500' : 'bg-rose-500')) }}"></div>
        </div>
        <div class="flex items-start gap-3 px-4 py-3">
            <div id="nsyncSystemToastIconWrap" class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl {{ $systemFlashToastTone === 'success' ? 'bg-emerald-50 text-emerald-600' : ($systemFlashToastTone === 'warning' ? 'bg-amber-50 text-amber-600' : ($systemFlashToastTone === 'info' ? 'bg-sky-50 text-sky-600' : 'bg-rose-50 text-rose-600')) }}">
                <svg id="nsyncSystemToastSuccessIcon" class="h-4 w-4 {{ $systemFlashToastTone === 'success' ? '' : 'hidden' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <svg id="nsyncSystemToastErrorIcon" class="h-4 w-4 {{ $systemFlashToastTone === 'success' ? 'hidden' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M4.93 19h14.14c1.54 0 2.5-1.67 1.73-3L13.73 4c-.77-1.33-2.69-1.33-3.46 0L3.2 16c-.77 1.33.19 3 1.73 3z"/>
                </svg>
            </div>
            <div class="min-w-0 flex-1">
                <p id="nsyncSystemToastLabel" class="text-xs font-black uppercase tracking-[0.18em] {{ $systemFlashToastTone === 'success' ? 'text-emerald-700' : ($systemFlashToastTone === 'warning' ? 'text-amber-700' : ($systemFlashToastTone === 'info' ? 'text-sky-700' : 'text-rose-700')) }}">{{ $systemFlashToastTone === 'success' ? 'Success' : ($systemFlashToastTone === 'warning' ? 'Warning' : ($systemFlashToastTone === 'info' ? 'Notice' : 'Failed')) }}</p>
                <p id="nsyncSystemToastMessage" class="mt-1 text-xs font-medium leading-5 text-slate-700">{{ $systemFlashMessage }}</p>
            </div>
            <button type="button" id="nsyncSystemToastClose"
                    class="rounded-lg p-1 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Dismiss notification">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>
</div>
<div id="nsyncSystemModalRoot"
     class="fixed inset-0 z-[200] hidden items-center justify-center bg-slate-950/55 p-4 backdrop-blur-sm"
     aria-hidden="true">
    <div class="w-full max-w-lg overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <div id="nsyncSystemModalHeader" class="px-6 py-5 border-b border-slate-100 bg-slate-50">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p id="nsyncSystemModalEyebrow" class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">System Message</p>
                    <h2 id="nsyncSystemModalTitle" class="mt-2 text-2xl font-black text-slate-900">Notice</h2>
                </div>
                <button type="button" id="nsyncSystemModalClose"
                        class="rounded-lg p-2 text-slate-400 hover:bg-white hover:text-slate-600"
                        aria-label="Close dialog">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="px-6 py-6">
            <p id="nsyncSystemModalMessage" class="text-base leading-7 text-slate-700"></p>
        </div>
        <div id="nsyncSystemModalActions" class="flex items-center justify-end gap-3 border-t border-slate-100 px-6 py-5">
            <button type="button" id="nsyncSystemModalCancel"
                    class="rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200 hidden">
                Cancel
            </button>
            <button type="button" id="nsyncSystemModalConfirm"
                    class="rounded-xl bg-nsync-green-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-nsync-green-700">
                OK
            </button>
        </div>
    </div>
</div>
<script>
    (() => {
        const toastRoot = document.getElementById('nsyncSystemToastRoot');
        const toastCard = document.getElementById('nsyncSystemToastCard');
        const toastAccent = document.getElementById('nsyncSystemToastAccent');
        const toastIconWrap = document.getElementById('nsyncSystemToastIconWrap');
        const toastSuccessIcon = document.getElementById('nsyncSystemToastSuccessIcon');
        const toastErrorIcon = document.getElementById('nsyncSystemToastErrorIcon');
        const toastLabel = document.getElementById('nsyncSystemToastLabel');
        const toastMessage = document.getElementById('nsyncSystemToastMessage');
        const toastCloseBtn = document.getElementById('nsyncSystemToastClose');
        const root = document.getElementById('nsyncSystemModalRoot');
        if (!root || !toastRoot) return;

        const header = document.getElementById('nsyncSystemModalHeader');
        const eyebrow = document.getElementById('nsyncSystemModalEyebrow');
        const titleEl = document.getElementById('nsyncSystemModalTitle');
        const messageEl = document.getElementById('nsyncSystemModalMessage');
        const closeBtn = document.getElementById('nsyncSystemModalClose');
        const cancelBtn = document.getElementById('nsyncSystemModalCancel');
        const confirmBtn = document.getElementById('nsyncSystemModalConfirm');
        const actions = document.getElementById('nsyncSystemModalActions');

        let confirmAction = null;
        let toastTimer = null;
        let toastAnimationFrame = null;
        const toastDuration = 4000;

        const toastPalette = {
            success: {
                accent: 'bg-emerald-500',
                border: 'border-emerald-100',
                iconWrap: 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600',
                label: 'text-xs font-black uppercase tracking-[0.18em] text-emerald-700',
                labelText: 'Success',
                showSuccessIcon: true,
            },
            warning: {
                accent: 'bg-amber-500',
                border: 'border-amber-100',
                iconWrap: 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600',
                label: 'text-xs font-black uppercase tracking-[0.18em] text-amber-700',
                labelText: 'Warning',
                showSuccessIcon: false,
            },
            info: {
                accent: 'bg-sky-500',
                border: 'border-sky-100',
                iconWrap: 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-600',
                label: 'text-xs font-black uppercase tracking-[0.18em] text-sky-700',
                labelText: 'Notice',
                showSuccessIcon: false,
            },
            error: {
                accent: 'bg-rose-500',
                border: 'border-rose-100',
                iconWrap: 'mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-rose-50 text-rose-600',
                label: 'text-xs font-black uppercase tracking-[0.18em] text-rose-700',
                labelText: 'Failed',
                showSuccessIcon: false,
            },
        };

        const palette = {
            success: {
                header: 'px-6 py-5 border-b border-green-100 bg-green-50',
                eyebrow: 'text-xs font-bold uppercase tracking-[0.18em] text-green-700',
                title: 'Success',
                eyebrowText: 'Completed',
                confirmClass: 'rounded-xl bg-green-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-green-700',
            },
            error: {
                header: 'px-6 py-5 border-b border-red-100 bg-red-50',
                eyebrow: 'text-xs font-bold uppercase tracking-[0.18em] text-red-700',
                title: 'Error',
                eyebrowText: 'Action Failed',
                confirmClass: 'rounded-xl bg-red-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-red-700',
            },
            warning: {
                header: 'px-6 py-5 border-b border-amber-100 bg-amber-50',
                eyebrow: 'text-xs font-bold uppercase tracking-[0.18em] text-amber-700',
                title: 'Warning',
                eyebrowText: 'Attention',
                confirmClass: 'rounded-xl bg-amber-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-amber-700',
            },
            info: {
                header: 'px-6 py-5 border-b border-sky-100 bg-sky-50',
                eyebrow: 'text-xs font-bold uppercase tracking-[0.18em] text-sky-700',
                title: 'Notice',
                eyebrowText: 'System Message',
                confirmClass: 'rounded-xl bg-sky-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-sky-700',
            },
            confirm: {
                header: 'px-6 py-5 border-b border-slate-100 bg-slate-50',
                eyebrow: 'text-xs font-bold uppercase tracking-[0.18em] text-slate-500',
                title: 'Confirm Action',
                eyebrowText: 'Please Confirm',
                confirmClass: 'rounded-xl bg-nsync-green-600 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-nsync-green-700',
            },
        };

        const closeToast = () => {
            toastRoot.classList.add('hidden');
            toastRoot.style.display = 'none';
            if (toastTimer) {
                clearTimeout(toastTimer);
                toastTimer = null;
            }
            if (toastAnimationFrame) {
                cancelAnimationFrame(toastAnimationFrame);
                toastAnimationFrame = null;
            }
            toastAccent.style.transform = 'scaleX(1)';
        };

        const openToast = ({ type = 'success', message = '' }) => {
            const tone = toastPalette[type] || toastPalette.error;

            toastCard.className = `pointer-events-auto overflow-hidden rounded-2xl border ${tone.border} bg-white/95 shadow-2xl ring-1 ring-black/5 backdrop-blur`;
            toastAccent.className = `h-1 w-full origin-left ${tone.accent}`;
            toastIconWrap.className = tone.iconWrap;
            toastLabel.className = tone.label;
            toastLabel.textContent = tone.labelText;
            toastMessage.textContent = message;

            toastSuccessIcon.classList.toggle('hidden', !tone.showSuccessIcon);
            toastErrorIcon.classList.toggle('hidden', tone.showSuccessIcon);

            toastRoot.classList.remove('hidden');
            toastRoot.style.display = 'block';

            if (toastTimer) {
                clearTimeout(toastTimer);
                toastTimer = null;
            }
            if (toastAnimationFrame) {
                cancelAnimationFrame(toastAnimationFrame);
                toastAnimationFrame = null;
            }

            const startedAt = performance.now();
            toastAccent.style.transformOrigin = 'left center';
            toastAccent.style.transform = 'scaleX(1)';

            const step = (now) => {
                const elapsed = now - startedAt;
                const progress = Math.max(0, 1 - (elapsed / toastDuration));
                toastAccent.style.transform = `scaleX(${progress})`;

                if (progress > 0) {
                    toastAnimationFrame = requestAnimationFrame(step);
                } else {
                    toastAnimationFrame = null;
                }
            };

            toastAnimationFrame = requestAnimationFrame(step);

            toastTimer = setTimeout(() => {
                closeToast();
            }, toastDuration);
        };

        if (@js(filled($systemFlashMessage) && filled($systemFlashToastTone))) {
            openToast({
                type: @js($systemFlashToastTone),
                message: @js($systemFlashMessage),
            });
        }

        const closeModal = () => {
            root.classList.add('hidden');
            root.classList.remove('flex');
            root.setAttribute('aria-hidden', 'true');
            confirmAction = null;
        };

        const openModal = ({ type = 'info', title, message = '', confirmLabel = 'OK', cancelLabel = 'Cancel', isConfirm = false, onConfirm = null }) => {
            const tone = palette[type] || palette.info;
            header.className = tone.header;
            eyebrow.className = tone.eyebrow;
            eyebrow.textContent = tone.eyebrowText;
            titleEl.textContent = title || tone.title;
            messageEl.textContent = message;

            confirmBtn.className = tone.confirmClass;
            confirmBtn.textContent = confirmLabel;

            if (isConfirm) {
                cancelBtn.classList.remove('hidden');
                cancelBtn.textContent = cancelLabel;
            } else {
                cancelBtn.classList.add('hidden');
            }

            confirmAction = typeof onConfirm === 'function' ? onConfirm : null;
            root.classList.remove('hidden');
            root.classList.add('flex');
            root.setAttribute('aria-hidden', 'false');
        };

        root.addEventListener('click', (event) => {
            if (event.target === root) closeModal();
        });
        closeBtn?.addEventListener('click', closeModal);
        cancelBtn?.addEventListener('click', closeModal);
        toastCloseBtn?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closeToast();
        });
        confirmBtn?.addEventListener('click', () => {
            if (confirmAction) {
                confirmAction();
                return;
            }
            closeModal();
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && !root.classList.contains('hidden')) {
                closeModal();
            }
        });

        document.querySelectorAll('form[data-confirm-modal="true"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();

                openModal({
                    type: 'confirm',
                    title: form.dataset.confirmTitle || 'Confirm Action',
                    message: form.dataset.confirmMessage || 'Do you want to continue?',
                    confirmLabel: form.dataset.confirmButton || 'Continue',
                    cancelLabel: form.dataset.cancelButton || 'Cancel',
                    isConfirm: true,
                    onConfirm: () => form.submit(),
                });
            });
        });

        window.NSyncSystemModal = {
            open: openModal,
            close: closeModal,
        };

        window.NSyncSystemToast = {
            open: openToast,
            close: closeToast,
        };
    })();
</script>

</body>
</html>
