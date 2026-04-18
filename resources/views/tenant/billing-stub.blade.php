<!DOCTYPE html>
<html>
<head>
    <title>Billing - NSync</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-6 py-12">
        <div class="bg-white rounded-lg shadow-sm border p-8 text-center">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">Billing &amp; Plans</h1>
            <p class="text-gray-600 mb-8">Coming soon! All core features are available on your Free plan.</p>
            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-6 rounded-xl">
                    <h3 class="text-xl font-bold mb-2">Free</h3>
                    <p class="text-2xl font-bold mb-4">$0/mo</p>
                    <ul class="space-y-2 text-sm mb-6">
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>Unlimited boards</li>
                        <li class="flex items-center"><svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg>Team collaboration</li>
                    </ul>
                    <span class="inline-block bg-white bg-opacity-20 px-4 py-2 rounded-full text-sm font-semibold">Current Plan</span>
                </div>
            </div>
            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 bg-gray-900 text-white font-medium rounded-lg hover:bg-gray-800 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
