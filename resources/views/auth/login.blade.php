<x-guest-layout>
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

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div class="space-y-2">
            <label class="block text-sm font-semibold text-gray-700">
                Email Address
            </label>
            <input
                type="email"
                name="email"
                value="{{ old('email') }}"
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
<div class="relative">
                <input
                    id="password"
                    type="password"
                    name="password"
                    placeholder="Enter password"
                    required
class="w-full px-4 pr-12 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm pr-12"
                    style="--tw-ring-color: var(--tenant-primary);"
                >
                <button
                    type="button"
                    onclick="togglePassword()"
                    class="absolute inset-y-0 right-0 pr-3 flex items-center"
                    title="Toggle password visibility"
                >
                    <svg id="eye-open" class="w-5 h-5 eye-icon text-gray-400 hover:text-gray-600 transition-colors hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg id="eye-closed" class="w-5 h-5 eye-icon text-gray-400 hover:text-gray-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L19.5 19.5" />
                    </svg>
                </button>
            </div>
            <script>
                function togglePassword() {
                    const passwordInput = document.getElementById('password');
                    const eyeOpen = document.getElementById('eye-open');
                    const eyeClosed = document.getElementById('eye-closed');
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        eyeClosed.classList.add('hidden');
                        eyeOpen.classList.remove('hidden');
                    } else {
                        passwordInput.type = 'password';
                        eyeClosed.classList.remove('hidden');
                        eyeOpen.classList.add('hidden');
                    }
                }
            </script>
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
                <a href="{{ route('register') }}" class="text-green-600 font-bold ml-1 hover:underline">
                    Create account
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>
