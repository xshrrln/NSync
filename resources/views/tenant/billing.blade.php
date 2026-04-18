<!DOCTYPE html>
<html>
<head>
    <title>Billing - NSync</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="py-5 bg-white min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center py-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 mb-0">Billing</h1>
                    <p class="text-gray-600 mb-0">Manage your subscription and plans</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-6 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <h1 class="text-3xl font-bold text-gray-900 mb-4">Billing & Plans</h1>
                <p class="text-gray-600 mb-8">Coming soon! All core features are available on your Free plan.</p>
                @php $tenant = auth()->user()->tenant; $planConfig = config("plans." . strtolower($tenant->plan ?? 'free'), []); @endphp
                
                <div class="space-y-6 mb-8">
                    <div class="bg-white p-8 rounded-2xl border border-gray-200 shadow-sm">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6">
                            <div>
                                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ ucfirst($tenant->plan ?? 'Free') }} Plan</h2>
                                <p class="text-3xl font-black text-gray-900">{{ $planConfig['price'] ?? 'Free Forever' }}</p>
                            </div>
                            <span class="px-4 py-2 bg-green-50 text-green-700 font-bold rounded-xl text-sm uppercase tracking-wide">Active Plan</span>
                        </div>
                        
                        <div class="grid md:grid-cols-2 gap-6 mt-8 pt-8 border-t border-gray-100">
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Members</h4>
                                <p class="text-2xl font-black text-gray-900">{{ $tenant->member_count }} / {{ $planConfig['members_limit'] ?? 'Unlimited' }}</p>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-2">Boards</h4>
                                <p class="text-2xl font-black text-gray-900">{{ $tenant->boards()->count() }} / {{ $planConfig['boards_limit'] ?? 'Unlimited' }}</p>
                            </div>
                        </div>
                    </div>

                    @if(($tenant->plan ?? 'free') === 'free')
                    <div class="bg-gradient-to-r from-purple-500 to-pink-600 text-white p-8 rounded-3xl text-center shadow-2xl">
                        <div class="max-w-2xl mx-auto">
                            <h3 class="text-2xl font-bold mb-4">Ready for more?</h3>
                            <p class="text-xl mb-8 opacity-95">Upgrade to Standard or Pro and unlock unlimited boards, advanced features, and priority support.</p>
                            <a href="#" class="inline-block bg-white text-purple-600 px-8 py-4 rounded-xl font-bold text-lg hover:shadow-xl transform hover:-translate-y-1 transition-all duration-300">
                                Upgrade Now → (Coming Soon)
                            </a>
                        </div>
                    </div>
                    @endif
                </div>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>




