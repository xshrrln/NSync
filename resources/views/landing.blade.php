<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NSync - Multi-Tenant Team Task Board Platform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html {
            scroll-behavior: smooth;
        }
        /* Custom classes for the scroll button animation */
        .scroll-top-btn {
            transition: all 0.3s ease;
            opacity: 0;
            visibility: hidden;
            transform: translateY(20px);
        }
        .scroll-top-btn.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
    </style>
</head>
<body class="antialiased bg-white text-gray-900">
    <div class="min-h-screen flex flex-col">
        <header class="w-full border-b border-gray-100 bg-white shadow-sm fixed top-0 inset-x-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex items-center justify-between gap-6">
                <div class="text-2xl font-black tracking-tight">
                    N<span class="text-green-600">S</span>ync
                </div>
                <nav class="flex items-center gap-4 text-sm font-semibold text-gray-800 flex-wrap justify-end">
                    <a href="#features" class="hover:text-gray-900">Features</a>
                    <a href="#pricing" class="hover:text-gray-900">Pricing</a>
                    <a href="{{ route('login') }}" class="px-4 py-2 rounded-full border border-gray-300 text-gray-800 hover:border-gray-400 hover:bg-gray-50 transition">Log in</a>
                    <a href="#pricing" class="px-4 py-2 rounded-full bg-green-600 text-white shadow hover:bg-green-700 transition">Get started</a>
                </nav>
            </div>
        </header>

        <main class="flex-1 relative">
            <button id="scrollToTop" class="scroll-top-btn fixed bottom-8 right-8 z-[60] p-4 bg-green-600 text-white rounded-full shadow-2xl hover:bg-green-700 focus:outline-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
                </svg>
            </button>

            <section class="relative overflow-hidden bg-gray-100 h-screen flex items-center pt-28">
                <div class="absolute inset-0 pointer-events-none">
                    <svg class="w-full h-full opacity-40" viewBox="0 0 1440 560" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <linearGradient id="wave" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" stop-color="#d9d9d9" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#cccccc" stop-opacity="0.3" />
                            </linearGradient>
                        </defs>
                        <path fill="url(#wave)" d="M0,400 C240,320 480,480 720,420 C960,360 1200,440 1440,360 L1440,560 L0,560 Z"></path>
                        <circle cx="200" cy="260" r="240" fill="none" stroke="#d0d0d0" stroke-width="1" />
                        <circle cx="1240" cy="260" r="260" fill="none" stroke="#d0d0d0" stroke-width="1" />
                    </svg>
                </div>
                <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-28 lg:py-36 text-center relative">
                    <h1 class="text-4xl md:text-6xl font-black tracking-tight text-gray-900 mb-2">
                        Welcome to NSync
                    </h1>
                    <p class="text-lg md:text-xl text-gray-700 mb-10 max-w-3xl mx-auto">
                        Secure multi-tenant workspaces with real-time boards, role controls, and analytics—built for teams that need speed and safety.
                    </p>
                    <div class="flex justify-center">
                        <a href="#features" class="px-8 py-3 bg-green-600 text-white font-semibold rounded-full shadow hover:bg-green-700">Learn more</a>
                    </div>
                </div>
            </section>

            <section id="features" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center justify-start scroll-mt-24" style="height: 89vh; padding-top: 10rem;">
                <div class="text-center mb-12">
                    <p class="text-xs uppercase tracking-widest text-green-700 font-semibold">Why NSync</p>
                    <h2 class="text-3xl font-black text-gray-900 mt-2">Everything you need to run teams</h2>
                </div>

                <div class="grid md:grid-cols-3 gap-10">
                    <div class="p-8 rounded-2xl border border-gray-100 shadow-sm h-full min-h-[220px]">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold mb-5">1</div>
                        <h3 class="text-xl font-bold mb-3">Isolated Tenants</h3>
                        <p class="text-sm text-gray-600">Separate databases per tenant with policy-based access and audit logging for peace of mind.</p>
                    </div>
                    <div class="p-8 rounded-2xl border border-gray-100 shadow-sm h-full min-h-[220px]">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold mb-5">2</div>
                        <h3 class="text-xl font-bold mb-3">Real-time Collaboration</h3>
                        <p class="text-sm text-gray-600">Live Kanban boards, role permissions, attachments, and checklists keep teams aligned.</p>
                    </div>
                    <div class="p-8 rounded-2xl border border-gray-100 shadow-sm h-full min-h-[220px]">
                        <div class="w-10 h-10 rounded-full bg-green-100 text-green-700 flex items-center justify-center font-bold mb-5">3</div>
                        <h3 class="text-xl font-bold mb-3">Reporting & Alerts</h3>
                        <p class="text-sm text-gray-600">Advanced reporting, activity logs, and configurable notifications for admins and supervisors.</p>
                    </div>
                </div>
            </section>

            <section id="pricing" class="bg-gray-50 border-y border-gray-100 flex flex-col items-center justify-start scroll-mt-24" style="min-height: 89vh; padding-top: 10rem; padding-bottom: 5rem;">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div class="text-center mb-12">
                        <p class="text-xs uppercase tracking-widest text-green-700 font-semibold">Pricing</p>
                        <h2 class="text-3xl md:text-4xl font-black text-gray-900 mt-2">Pick a plan that fits</h2>
                        <p class="text-gray-600 mt-3">Simple tiers, predictable pricing, no hidden fees.</p>
                    </div>
                    <div class="grid md:grid-cols-3 gap-8 items-stretch">
                        @foreach(config('plans') as $plan => $features)
                            <div class="bg-white border rounded-2xl p-8 shadow-lg hover:shadow-2xl transition-all duration-300 hover:-translate-y-2 h-full flex flex-col">
                                <h3 class="text-2xl font-bold text-gray-900 mb-4 capitalize">{{ ucfirst($plan) }} Plan</h3>
                                @if($plan === 'free')
                                    <div class="text-4xl font-bold text-green-600 mb-6">Free Trial</div>
                                    <div class="text-3xl font-bold text-gray-900 mb-8">14 Days</div>
                                @else
                                    <div class="text-4xl font-bold text-gray-900 mb-6">
                                        {{ $features['price'] ?? '₱799' }}/mo
                                    </div>
                                @endif
                                <ul class="space-y-3 mb-8">
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                        </svg>
                                        Up to {{ $features['members_limit'] }} members
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                        </svg>
                                        {{ $features['boards_limit'] === 999 ? 'Unlimited' : $features['boards_limit'] }} boards
                                    </li>
                                    <li class="flex items-center">
                                        <svg class="w-5 h-5 text-green-500 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                        </svg>
                                        {{ $features['storage_limit'] }}MB storage
                                    </li>
                                    @foreach($features['features'] ?? [] as $feature)
                                        <li class="flex items-center text-sm">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                                            </svg>
                                            {{ ucwords(str_replace('-', ' ', $feature)) }}
                                        </li>
                                    @endforeach
                                </ul>
                                <div class="mt-auto">
                                    @if($plan === 'free')
                                        <a href="{{ route('register') }}?plan=free" class="w-full block bg-green-600 text-white text-center py-4 rounded-xl font-bold hover:bg-green-700">Start Free Trial</a>
                                    @else
                                        <a href="{{ route('register') }}?plan={{ $plan }}" class="w-full block border-2 border-gray-200 text-gray-900 text-center py-4 rounded-xl font-bold hover:bg-gray-50">Choose {{ ucfirst($plan) }}</a>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        </main>

        <footer class="bg-gray-900 text-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid md:grid-cols-4 gap-8">
                <div class="md:col-span-2">
                    <div class="text-2xl font-black tracking-tight mb-3">N<span class="text-green-400">S</span>ync</div>
                    <p class="text-sm text-gray-400">Secure, scalable workspaces with the collaboration tools your teams need.</p>
                </div>
                <div>
                    <p class="text-sm font-semibold mb-3 text-white">Product</p>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#features" class="hover:text-white">Features</a></li>
                        <li><a href="#pricing" class="hover:text-white">Pricing</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white">Log in</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-sm font-semibold mb-3 text-white">Company</p>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="{{ route('register') }}" class="hover:text-white">Get started</a></li>
                        <li><a href="{{ route('login') }}" class="hover:text-white">Support</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 text-xs text-gray-500 py-4 text-center">
                © {{ date('Y') }} NSync. All rights reserved.
            </div>
        </footer>
    </div>

    <script>
        const scrollBtn = document.getElementById("scrollToTop");

        window.onscroll = function() {
            if (document.body.scrollTop > 300 || document.documentElement.scrollTop > 300) {
                scrollBtn.classList.add("show");
            } else {
                scrollBtn.classList.remove("show");
            }
        };

        scrollBtn.onclick = function() {
            window.scrollTo({top: 0, behavior: 'smooth'});
        };
    </script>
</body>
</html>