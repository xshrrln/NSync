<x-guest-layout>
    <div class="mx-auto w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 shadow-sm">
        <h1 class="text-2xl font-black text-slate-900">Two-Factor Verification</h1>
        <p class="mt-2 text-sm text-slate-600">
            Enter the 6-digit verification code sent to your email.
        </p>

        @if(session('status'))
            <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.verify') }}" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="code" class="mb-2 block text-sm font-semibold text-slate-700">Verification Code</label>
                <input
                    id="code"
                    name="code"
                    type="text"
                    inputmode="numeric"
                    maxlength="6"
                    autocomplete="one-time-code"
                    placeholder="Enter 6-digit code"
                    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-center text-lg tracking-[0.4em] focus:border-transparent focus:ring-2 focus:ring-emerald-500"
                    required
                >
                @error('code')
                    <p class="mt-2 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700">
                Verify and Continue
            </button>
        </form>

        <form method="POST" action="{{ route('two-factor.resend') }}" class="mt-3">
            @csrf
            <button type="submit" class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Resend Code
            </button>
        </form>
    </div>
</x-guest-layout>

