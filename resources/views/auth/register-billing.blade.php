<x-guest-layout>
    <div class="text-center mb-8">
        <p class="text-sm font-semibold uppercase tracking-wide text-green-700">Registration</p>
        <h2 class="text-3xl font-bold text-gray-900 tracking-tight mt-2">Complete Billing</h2>
        <p class="text-base text-gray-600 mt-2">Finish setting up your workspace by adding your billing details.</p>
    </div>

    <form method="POST" action="{{ route('register.billing.store', absolute: false) }}" class="space-y-6">
        @csrf

        <div class="space-y-2">
            <label for="card_holder" class="block text-sm font-semibold text-gray-700">Cardholder Name</label>
            <input
                id="card_holder"
                type="text"
                name="card_holder"
                value="{{ old('card_holder', $data['name']) }}"
                placeholder="Enter cardholder name"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('card_holder')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="space-y-2">
            <label for="card_number" class="block text-sm font-semibold text-gray-700">Card Number</label>
            <input
                id="card_number"
                type="text"
                name="card_number"
                value="{{ old('card_number') }}"
                placeholder="1234 5678 9012 3456"
                inputmode="numeric"
                autocomplete="cc-number"
                maxlength="19"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
                oninput="formatCardNumber(this)"
            >
            @error('card_number')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-2">
                <label for="card_expiry" class="block text-sm font-semibold text-gray-700">Expiry</label>
                <input
                    id="card_expiry"
                    type="text"
                    name="card_expiry"
                    value="{{ old('card_expiry') }}"
                    placeholder="MM/YY"
                    inputmode="numeric"
                    autocomplete="cc-exp"
                    maxlength="5"
                    required
                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                    style="--tw-ring-color: var(--tenant-primary);"
                    oninput="formatCardExpiry(this)"
                >
                @error('card_expiry')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-2">
                <label for="card_cvv" class="block text-sm font-semibold text-gray-700">CVV</label>
                <input
                    id="card_cvv"
                    type="text"
                    name="card_cvv"
                    value="{{ old('card_cvv') }}"
                    placeholder="123"
                    required
                    class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                    style="--tw-ring-color: var(--tenant-primary);"
                >
                @error('card_cvv')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="space-y-2">
            <label for="billing_country" class="block text-sm font-semibold text-gray-700">Billing Country</label>
            <input
                id="billing_country"
                type="text"
                name="billing_country"
                value="{{ old('billing_country', 'Philippines') }}"
                placeholder="Enter billing country"
                required
                class="w-full px-4 py-3 bg-white border border-gray-300 rounded-xl text-gray-900 placeholder-gray-500 focus:ring-2 focus:border-transparent outline-none text-base transition-all shadow-sm"
                style="--tw-ring-color: var(--tenant-primary);"
            >
            @error('billing_country')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="rounded-2xl border border-gray-200 bg-gray-50 px-5 py-4">
            <p class="text-sm font-semibold text-gray-700">Workspace Summary</p>
            <div class="mt-3 space-y-2 text-sm text-gray-600">
                <p><span class="font-medium text-gray-800">Organization:</span> {{ $data['org_name'] }}</p>
                <p><span class="font-medium text-gray-800">Workspace:</span> {{ $data['org_domain'] }}.localhost</p>
                <p><span class="font-medium text-gray-800">Admin Email:</span> {{ $data['email'] }}</p>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <a class="text-sm text-gray-600 hover:text-gray-900 underline underline-offset-4" href="{{ route('register', absolute: false) }}">
                Back to organization details
            </a>

            <button
                type="submit"
                class="px-6 py-3 rounded-xl text-white font-semibold text-sm shadow-md transition-all"
                style="background-color: var(--tenant-primary);"
                onmouseover="this.style.opacity='0.9'"
                onmouseout="this.style.opacity='1'">
                Create Workspace
            </button>
        </div>
    </form>

    <script>
        function formatCardNumber(input) {
            const digits = input.value.replace(/\D/g, '').slice(0, 16);
            const groups = digits.match(/.{1,4}/g) || [];
            input.value = groups.join(' ');
        }

        function formatCardExpiry(input) {
            const digits = input.value.replace(/\D/g, '').slice(0, 4);

            if (digits.length <= 2) {
                input.value = digits;
                return;
            }

            input.value = digits.slice(0, 2) + '/' + digits.slice(2);
        }
    </script>
</x-guest-layout>
