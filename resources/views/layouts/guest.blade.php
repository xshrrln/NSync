<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NSync</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon-logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @php
            $tenant = app()->has('currentTenant') ? app('currentTenant') : null;
            $theme = $tenant?->theme ?? ['primary' => '#16A34A', 'secondary' => '#FFFFFF'];
            $primary = $theme['primary'] ?? '#16A34A';
            $secondary = $theme['secondary'] ?? '#FFFFFF';
        @endphp
        :root {
            --tenant-primary: {{ $primary }};
            --tenant-secondary: {{ $secondary }};
        }
        .btn-primary {
            background-color: var(--tenant-primary);
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .focus\:ring-primary:focus {
            --tw-ring-color: var(--tenant-primary);
        }
        .text-primary {
            color: var(--tenant-primary);
        }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased text-gray-900">
    <div class="min-h-screen flex flex-col justify-center items-center p-6">
        
        <div class="mb-12 text-center group">
            <h1 class="text-7xl font-black tracking-tighter text-gray-900 transition-all group-hover:tracking-normal">
                N<span class="text-primary">S</span>YNC
            </h1>
            <div class="h-1 w-12 mx-auto mt-2 rounded-full" style="background-color: var(--tenant-primary);"></div>
            <p class="text-gray-500 text-[10px] uppercase tracking-[0.6em] mt-4 font-bold">Team Workspace</p>
        </div>

        <div class="w-full sm:max-w-md bg-white border border-gray-200 p-10 rounded-[2.5rem] shadow-lg">
            {{ $slot }}
        </div>
        
        <div class="mt-12 flex items-center gap-4 opacity-30 hover:opacity-100 transition-opacity">
            <span class="h-px w-8 bg-gray-300"></span>
            <p class="text-gray-500 text-[10px] uppercase tracking-widest font-bold font-mono italic">&copy; 2026 NSYNC_SYSTEM</p>
            <span class="h-px w-8 bg-gray-300"></span>
        </div>
    </div>
</body>
</html>
