<x-guest-layout>
    @if (! empty($logoutHostUrl ?? null))
        <img src="{{ $logoutHostUrl }}" alt="" class="hidden" aria-hidden="true">
    @endif

    @if (request()->boolean('accepted'))
        <div class="mb-6 p-4 rounded-lg border text-sm" style="background-color: color-mix(in srgb, var(--tenant-primary) 10%, white 90%); border-color: color-mix(in srgb, var(--tenant-primary) 25%, white 75%); color: color-mix(in srgb, var(--tenant-primary) 80%, black 20%);">
            Invite accepted successfully. Sign in to continue to your workspace.
        </div>
    @endif
    @if (session('success'))
        <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded-lg">
            <p class="text-green-700 text-sm font-medium">{{ session('success') }}</p>
            <p class="text-green-600 text-xs mt-1">Check your email for login credentials and your organization workspace URL.</p>
        </div>
    @endif
    @if (session('info'))
        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-blue-700 text-sm font-medium">{{ session('info') }}</p>
        </div>
    @endif
    @if (session('error'))
        <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
            <p class="text-red-700 text-sm font-medium">{{ session('error') }}</p>
        </div>
    @endif



    <div class="text-center mb-10">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Welcome Back</h2>
        <p class="text-sm text-gray-600 mt-2">Please enter your details to sign in.</p>
    </div>

    <form method="POST" action="{{ route('login', absolute: false) }}" class="space-y-6">
        @csrf

        <div class="space-y-2">
            <label class="block text-sm font-semibold text-gray-700">
                Email Address
            </label>
            <input
                type="email"
                name="email"
                value="{{ old('email', request('email')) }}"
                placeholder="example@example.com"
                required
                autofocus
class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-semibold text-gray-700">
                Password
            </label>
            <x-password-input
                id="password"
                name="password"
                placeholder="Enter password"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            />
            @error('password')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between text-[11px]">
            <label class="flex items-center gap-2 cursor-pointer group">
                <input
                    type="checkbox"
                    name="remember"
                    class="w-4 h-4 rounded border-gray-300 text-primary bg-white focus:ring-primary"
                >
                <span class="text-gray-600 group-hover:text-gray-700 transition-colors">
                    Remember me
                </span>
            </label>

            <a href="{{ route('password.request') }}"
               class="text-primary font-bold hover:opacity-80 transition-opacity"
               style="color: var(--tenant-primary);">
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="w-full text-white font-bold py-3.5 rounded-xl transition-all shadow-md text-sm active:scale-[0.98]"
            style="background-color: var(--tenant-primary);"
            onmouseover="this.style.opacity='0.9'"
            onmouseout="this.style.opacity='1'">
            Sign in
        </button>

        <div class="relative py-2">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center text-sm font-semibold text-gray-400">
                <span class="bg-white px-4">Or</span>
            </div>
        </div>

        <a href="{{ route('google.redirect') }}" class="w-full flex items-center justify-center gap-3 py-3 border border-gray-300 rounded-xl hover:bg-gray-50 transition-all text-gray-700 font-semibold text-sm">
            <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-4 w-4" alt="Google">
            Sign in with Google
        </a>

        <div class="text-center pt-6 border-t border-gray-200">
            <p class="text-sm text-gray-600">
                New to NSync?
                <a href="{{ route('register', absolute: false) }}" class="text-green-600 font-bold ml-1 hover:underline">
                    Create account
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>
