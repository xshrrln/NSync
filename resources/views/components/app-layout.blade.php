<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'NSync') }}</title>
        <style>
        @php
            $tenant = app()->has('currentTenant') ? app('currentTenant') : null;
            $theme = $tenant?->theme ?? ['primary' => '#16a34a', 'secondary' => '#FFFFFF'];
            $primary = $theme['primary'] ?? '#16a34a';
            $secondary = $theme['secondary'] ?? '#FFFFFF';
        @endphp
        :root {
            --tenant-primary: {{ $primary }};
            --tenant-secondary: {{ $secondary }};
        }
        /* Map primary color onto common utility classes used across the tenant app */
        .bg-emerald-600, .bg-nsync-green-600, .bg-green-600 {
            background-color: var(--tenant-primary) !important;
        }
        .hover\:bg-emerald-700:hover, .hover\:bg-nsync-green-700:hover, .hover\:bg-green-700:hover {
            background-color: color-mix(in srgb, var(--tenant-primary) 90%, #000 10%) !important;
        }
        .text-green-600, .text-emerald-600, .text-nsync-green-600 {
            color: var(--tenant-primary) !important;
        }
        .border-green-600, .border-emerald-600, .border-nsync-green-600 {
            border-color: var(--tenant-primary) !important;
        }
        .ring-green-500, .ring-emerald-500, .ring-nsync-green-500 {
            --tw-ring-color: var(--tenant-primary) !important;
        }
        /* Secondary backgrounds */
        .bg-nsync-secondary {
            background-color: var(--tenant-secondary) !important;
        }
        </style>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-white">
            @include('layouts.navigation')

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
