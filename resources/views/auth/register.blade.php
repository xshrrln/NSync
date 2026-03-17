<x-guest-layout>

<div class="text-center mb-8">
    <h2 class="text-xl font-bold text-white">Create your account</h2>
    <p class="text-xs text-slate-500 mt-2">
        Join the NSync workspace to start syncing your team.
    </p>
</div>

<form method="POST" action="{{ route('register') }}" class="space-y-6">
@csrf

<!-- Name -->
<div class="space-y-2">
    <label class="block text-xs font-semibold text-slate-300">
        Full Name
    </label>

    <input
        type="text"
        name="name"
        value="{{ old('name') }}"
        placeholder="{{ old('name') ? '' : 'Enter your full name' }}"
        required
        class="w-full px-4 py-3 bg-slate-800/40 border border-slate-700/50 rounded-xl text-slate-200 placeholder-slate-600 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm"
    >
    @error('name')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

<!-- Email -->
<div class="space-y-2">
    <label class="block text-xs font-semibold text-slate-300">
        Email
    </label>

    <input
        type="email"
        name="email"
        value="{{ old('email') }}"
        placeholder="{{ old('email') ? '' : 'Enter your email' }}"
        required
        class="w-full px-4 py-3 bg-slate-800/40 border border-slate-700/50 rounded-xl text-slate-200 placeholder-slate-600 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm"
    >
    @error('email')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

<!-- Password -->
<div class="space-y-2">
    <label class="block text-xs font-semibold text-slate-300">
        Password
    </label>

    <input
        type="password"
        name="password"
        value="{{ old('password') }}"
        placeholder="{{ old('password') ? '' : 'Enter password' }}"
        required
        class="w-full px-4 py-3 bg-slate-800/40 border border-slate-700/50 rounded-xl text-slate-200 placeholder-slate-600 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm"
    >
    @error('password')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

<!-- Confirm Password -->
<div class="space-y-2">
    <label class="block text-xs font-semibold text-slate-300">
        Confirm Password
    </label>

    <input
        type="password"
        name="password_confirmation"
        value="{{ old('password_confirmation') }}"
        placeholder="{{ old('password_confirmation') ? '' : 'Confirm password' }}"
        required
        class="w-full px-4 py-3 bg-slate-800/40 border border-slate-700/50 rounded-xl text-slate-200 placeholder-slate-600 focus:ring-2 focus:ring-blue-600 focus:border-transparent outline-none text-sm"
    >
    @error('password_confirmation')
        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
    @enderror
</div>

<!-- Register button -->
<button
type="submit"
class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-xl transition text-sm">
Create Account
</button>

<div class="text-center pt-6 border-t border-slate-800">
<p class="text-xs text-slate-500">
Already have an account?
<a href="{{ route('login') }}" class="text-blue-500 font-semibold ml-1">
Log in
</a>
</p>
</div>

</form>

</x-guest-layout>