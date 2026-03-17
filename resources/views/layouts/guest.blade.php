<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NSync</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 font-sans antialiased text-slate-200">
    <div class="min-h-screen flex flex-col justify-center items-center p-6">
        
        <div class="mb-12 text-center group">
            <h1 class="text-7xl font-black tracking-tighter text-white transition-all group-hover:tracking-normal">
                N<span class="text-blue-600">S</span>YNC
            </h1>
            <div class="h-1 w-12 bg-blue-600 mx-auto mt-2 rounded-full"></div>
            <p class="text-slate-500 text-[10px] uppercase tracking-[0.6em] mt-4 font-bold">Team Workspace</p>
        </div>

        <div class="w-full sm:max-w-md bg-slate-900/40 backdrop-blur-2xl border border-slate-800/50 p-10 rounded-[2.5rem] shadow-2xl">
            {{ $slot }}
        </div>
        
        <div class="mt-12 flex items-center gap-4 opacity-30 hover:opacity-100 transition-opacity">
            <span class="h-px w-8 bg-slate-700"></span>
            <p class="text-slate-500 text-[10px] uppercase tracking-widest font-bold font-mono italic">&copy; 2026 NSYNC_SYSTEM</p>
            <span class="h-px w-8 bg-slate-700"></span>
        </div>
    </div>
</body>
</html>