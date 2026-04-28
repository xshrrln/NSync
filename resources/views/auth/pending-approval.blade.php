<x-guest-layout>
    <div class="text-center space-y-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full border border-emerald-100 bg-emerald-50">
            <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900">Registration Submitted</h2>

        @if ($message = session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-700">
                {{ $message }}
            </div>
        @endif

        @if ($message = session('warning'))
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-700">
                {{ $message }}
            </div>
        @endif

        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-6 space-y-3">
            <p class="text-sm text-gray-700">
                Your organization registration is <strong>pending admin approval</strong>.
            </p>
            <p class="text-sm text-gray-600">
                Once approved, you will receive an email with:
            </p>
            <ul class="text-left space-y-2 text-sm text-gray-600">
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                    </svg>
                    Your workspace URL
                </li>
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                    </svg>
                    Login credentials (email & temporary password)
                </li>
                <li class="flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"></path>
                    </svg>
                    Getting started guide
                </li>
            </ul>
        </div>

        <div class="pt-4 space-y-3">
            <p class="text-xs text-gray-500">
                Check your email regularly for updates. This usually takes 24-48 hours.
            </p>
            <div class="flex gap-3 justify-center">
                <a href="https://mail.google.com" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white transition hover:bg-emerald-700">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    Open Gmail
                </a>
                <a href="{{ route('login', absolute: false) }}" class="inline-flex items-center rounded-lg border border-emerald-600 px-6 py-2 text-sm font-medium text-emerald-700 transition hover:text-emerald-800">
                    &larr; Back to Login
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
