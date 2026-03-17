<x-guest-layout>
    <div class="text-center mb-10">
        <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Welcome Back</h2>
        <p class="text-sm text-slate-500 mt-2">Please enter your details to sign in.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Email Address
            </label>
            <input
                type="email"
                name="email"
                placeholder="example@example.com"
                required
                autofocus
                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm transition-all shadow-sm"
            >
        </div>

        <div class="space-y-2">
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                Password
            </label>
            <input
                type="password"
                name="password"
                placeholder="Enter password"
                required
                class="w-full px-4 py-3 bg-white border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm transition-all shadow-sm"
            >
        </div>

        <div class="flex items-center justify-between text-[11px]">
            <label class="flex items-center gap-2 cursor-pointer group">
                <input
                    type="checkbox"
                    name="remember"
                    class="w-4 h-4 rounded border-slate-300 text-blue-600 bg-white focus:ring-blue-500"
                >
                <span class="text-slate-500 group-hover:text-slate-700 transition-colors">
                    Remember me
                </span>
            </label>

            <a href="{{ route('password.request') }}"
               class="text-blue-600 font-bold hover:text-blue-700 transition-colors">
                Forgot password?
            </a>
        </div>

        <button
            type="submit"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3.5 rounded-xl transition-all shadow-md shadow-blue-100 text-sm active:scale-[0.98]">
            Sign in
        </button>

        <div class="relative py-2">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-slate-100"></div>
            </div>
            <div class="relative flex justify-center text-[10px] uppercase tracking-widest font-bold text-slate-400">
                <span class="bg-white px-4">Or</span>
            </div>
        </div>

<a href="{{ route('google.redirect') }}" class="w-full flex items-center justify-center gap-3 py-3 border border-slate-200 rounded-xl hover:bg-slate-50 transition-all text-slate-700 font-semibold text-sm">
                <img src="https://www.svgrepo.com/show/475656/google-color.svg" class="h-4 w-4" alt="Google">
                Sign in with Google
            </a>

        <div class="text-center pt-6 border-t border-slate-100">
            <p class="text-sm text-slate-500">
                New to NSync?
                <a href="{{ route('register') }}" class="text-blue-600 font-bold ml-1 hover:underline">
                    Create account
                </a>
            </p>
        </div>
    </form>
</x-guest-layout>