@php
    $selectedPlanKey = old('plan', $prefill['plan'] ?? request()->query('plan', 'free'));
    $selectedPlan = $plans[$selectedPlanKey] ?? reset($plans);
    $selectedPlanName = $selectedPlan['name'] ?? ucfirst((string) $selectedPlanKey);
    $selectedPlanPrice = $selectedPlan['price'] ?? '';
    $selectedPlanMembers = $selectedPlan['members_limit'] ?? null;
@endphp

<x-guest-layout>
    <div class="text-center mb-8">
        <h2 class="text-3xl font-bold text-gray-900 tracking-tight">Create Your Organization</h2>
        <p class="text-base text-gray-600 mt-2">Register your team workspace and choose your plan.</p>
    </div>

    <form method="POST" action="{{ route('register.store') }}" class="space-y-6">
        @csrf
        <input type="hidden" name="plan" value="{{ $selectedPlanKey }}">

        <div class="space-y-2">
            <label for="org_name" class="block text-sm font-semibold text-gray-700">Organization Name</label>
            <input
                id="org_name"
                type="text"
                name="org_name"
                value="{{ old('org_name', $prefill['org_name'] ?? $prefill['organization'] ?? '') }}"
                placeholder="Enter organization name"
                required
                autofocus
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('org_name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <label for="org_address" class="block text-sm font-semibold text-gray-700">Organization Address</label>
                <input
                    id="org_address"
                    type="text"
                    name="org_address"
                    value="{{ old('org_address', $prefill['org_address'] ?? $prefill['address'] ?? '') }}"
                    placeholder="Enter address"
                    required
                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                    style="--tw-ring-color: var(--tenant-primary);"
                >
                @error('org_address')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="org_domain" class="block text-sm font-semibold text-gray-700">Organization Domain</label>
                <input
                    id="org_domain"
                    type="text"
                    name="org_domain"
                    value="{{ old('org_domain', $prefill['org_domain'] ?? '') }}"
                    placeholder="yourorg"
                    required
                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                    style="--tw-ring-color: var(--tenant-primary);"
                >
                <p class="text-sm text-gray-500">Workspace URL: <span class="font-medium text-gray-700">{{ old('org_domain', $prefill['org_domain'] ?? 'yourorg') }}.localhost</span></p>
                @error('org_domain')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label for="name" class="block text-sm font-semibold text-gray-700">Admin Full Name</label>
            <input
                id="name"
                type="text"
                name="name"
                value="{{ old('name', $prefill['name'] ?? '') }}"
                placeholder="Enter your full name"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('name')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="email" class="block text-sm font-semibold text-gray-700">Admin Email</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email', $prefill['email'] ?? '') }}"
                placeholder="Enter your email"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('email')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-2xl border border-green-200 bg-green-50 px-5 py-4">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div>
                    <p class="text-sm font-semibold text-gray-700">Selected Plan</p>
                    <h3 class="text-xl font-bold text-gray-900 mt-1">{{ $selectedPlanName }}</h3>
                </div>
                <a href="{{ route('landing') }}#pricing" class="text-sm font-semibold text-green-700 hover:underline">
                    Change plan
                </a>
            </div>
            <div class="flex items-end justify-between gap-4">
                <div class="text-sm text-gray-600">
                    @if($selectedPlanMembers)
                        {{ number_format($selectedPlanMembers) }} users included
                    @endif
                </div>
                <div class="text-2xl font-bold text-green-600">{{ $selectedPlanPrice }}</div>
            </div>
            @error('plan')
                <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-gray-600 hover:text-gray-900 underline underline-offset-4" href="{{ route('login') }}">
                Already registered?
            </a>

            <button
                type="submit"
                class="px-6 py-3 rounded-xl text-white font-semibold text-sm shadow-md transition-all"
                style="background-color: var(--tenant-primary);"
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'">
                Continue
            </button>
        </div>
    </form>
</x-guest-layout>
