@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-widest text-nsync-green-700 font-semibold">Tenant Management</p>
            <h1 class="text-3xl font-black text-gray-900 mt-1">Edit {{ $tenant->name }}</h1>
            <p class="text-gray-600 mt-1">Enable or disable features for this organization</p>
        </div>
        <a href="{{ route('admin.tenants.index') }}" class="px-6 py-2.5 bg-nsync-green-600 text-white text-sm font-semibold rounded-xl hover:bg-nsync-green-700 transition-all shadow-lg">Back to Tenants</a>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-100 bg-green-50 text-green-800 px-4 py-3 text-sm font-semibold">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-100 bg-red-50 text-red-700 px-4 py-3 text-sm font-semibold">
            Please review the form errors below.
        </div>
    @endif

    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Tenant Details</h3>
        <p class="text-sm text-gray-600 mb-6">Update tenant identity and lifecycle status.</p>

        <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="space-y-4">
            @csrf @method('PATCH')
            <input type="hidden" name="tenant_details" value="1">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Organization Name</label>
                    <input type="text" name="name" value="{{ old('name', $tenant->name) }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500" required>
                    @error('name') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Workspace Domain</label>
                    <input type="text" name="domain" value="{{ old('domain', $tenant->domain) }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500" required>
                    @error('domain') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tenant Admin</label>
                    <input type="text" name="tenant_admin" value="{{ old('tenant_admin', $tenant->tenant_admin) }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500" required>
                    @error('tenant_admin') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Tenant Admin Email</label>
                    <input type="email" name="tenant_admin_email" value="{{ old('tenant_admin_email', $tenant->tenant_admin_email) }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500" required>
                    @error('tenant_admin_email') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500">
                        @foreach(['pending', 'active', 'disabled'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $tenant->status) === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Address</label>
                    <input type="text" name="address" value="{{ old('address', $tenant->address) }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500">
                    @error('address') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $tenant->start_date ? \Illuminate\Support\Carbon::parse($tenant->start_date)->format('Y-m-d') : '') }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500">
                    @error('start_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Due Date</label>
                    <input type="date" name="due_date" value="{{ old('due_date', $tenant->due_date ? \Illuminate\Support\Carbon::parse($tenant->due_date)->format('Y-m-d') : '') }}" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500">
                    @error('due_date') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="px-6 py-3 bg-nsync-green-600 text-white font-bold rounded-xl hover:bg-nsync-green-700 transition-all shadow-lg whitespace-nowrap">
                    Update Tenant
                </button>
            </div>
        </form>
    </div>

    <!-- Plan Upgrade Section -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
        <h3 class="text-xl font-bold text-gray-900 mb-4">Subscription Plan</h3>
        <p class="text-sm text-gray-600 mb-6">Upgrade or change billing plan. Changes take effect immediately.</p>

        <form method="POST" action="{{ route('admin.tenants.upgrade-plan', $tenant) }}" class="flex items-end gap-4 max-w-md">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Current Plan: <span class="font-black text-lg capitalize">{{ $tenant->plan }}</span></label>
                <select name="plan" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-nsync-green-500">
                    <option value="free" {{ $tenant->plan === 'free' ? 'selected' : '' }}>Free</option>
                    <option value="standard" {{ $tenant->plan === 'standard' ? 'selected' : '' }}>Standard (PHP 799/mo)</option>
                    <option value="pro" {{ $tenant->plan === 'pro' ? 'selected' : '' }}>Pro (PHP 1,499/mo)</option>
                </select>
            </div>
            <button type="submit" class="px-6 py-3 bg-nsync-green-600 text-white font-bold rounded-xl hover:bg-nsync-green-700 transition-all shadow-lg whitespace-nowrap">
                Update Plan
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Summary card -->
        <div class="bg-white rounded-3xl shadow-xl border border-gray-100 p-8 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-widest text-green-600 font-semibold">Tenant</p>
                    <h2 class="text-2xl font-black text-gray-900">{{ $tenant->name }}</h2>
                </div>
                <span class="px-3 py-1 text-xs font-bold rounded-full bg-nsync-green-50 text-nsync-green-700">{{ ucfirst($tenant->status) }}</span>
            </div>
            <div class="space-y-2 text-sm text-gray-700">
                <p><span class="font-semibold">Domain:</span> {{ $tenant->domain }}</p>
                <p><span class="font-semibold">Plan:</span> {{ ucfirst($tenant->plan) }}</p>
                <p><span class="font-semibold">Created:</span> {{ $tenant->created_at?->format('M d, Y') ?? '-' }}</p>
                <p><span class="font-semibold">Start / Due:</span> {{ $tenant->start_date ?? '-' }} / {{ $tenant->due_date ?? '-' }}</p>
            </div>
            <div class="space-y-3">
                <p class="text-xs uppercase tracking-widest text-gray-500">Tenant Admin</p>
                <div class="p-4 rounded-xl bg-green-50/60 border border-green-100">
                    <p class="font-semibold text-gray-900">{{ $tenant->tenant_admin }}</p>
                    <p class="text-xs text-gray-500">{{ $tenant->tenant_admin_email }}</p>
                </div>
            </div>
        </div>

        <!-- Members list -->
        <div class="lg:col-span-2 bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
            <div class="p-8 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 mb-1">Members</h3>
                    <p class="text-sm text-gray-600">{{ $tenant->users->count() }} people in this organization</p>
                </div>
            </div>
            <div class="p-6">
                <div class="space-y-3">
                    @forelse($tenant->users as $user)
                        <div class="flex items-center justify-between px-4 py-3 rounded-xl border border-gray-100 bg-gray-50">
                            <div>
                                <p class="font-semibold text-gray-900">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500">{{ $user->email }}</p>
                            </div>
                            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-nsync-green-50 text-nsync-green-700">
                                {{ $user->roles->pluck('name')->implode(', ') ?: 'Member' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No members recorded for this tenant.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Feature toggles -->
    <div class="bg-white rounded-3xl shadow-xl border border-gray-100 overflow-hidden">
        <div class="p-8 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
            <div>
                <h3 class="text-xl font-bold text-gray-900 mb-2">Feature Flags</h3>
                <p class="text-sm text-gray-600">Enable or disable features available to this tenant by category.</p>
            </div>
        </div>
        <div class="p-8">
            <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}">
                @csrf @method('PATCH')
                <div class="space-y-6">
                    @foreach($featureCategories as $categoryKey => $category)
                        <div class="bg-gray-50 rounded-2xl border border-gray-100/70">
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">{{ $category['label'] ?? ucfirst($categoryKey) }}</p>
                                    <p class="text-sm text-gray-600">{{ count($category['features'] ?? []) }} features</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border border-nsync-green-200 bg-nsync-green-50 px-3 py-1.5 text-xs font-semibold text-nsync-green-700 hover:bg-nsync-green-100"
                                        onclick="toggleFeatureCategory(@js($categoryKey), true)"
                                    >
                                        Select All
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50"
                                        onclick="toggleFeatureCategory(@js($categoryKey), false)"
                                    >
                                        Clear
                                    </button>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @foreach($category['features'] as $featureKey => $meta)
                                    @php
                                        $isDefault = in_array($featureKey, $planFeatures);
                                        $isEnabled = in_array($featureKey, $enabledFeatures);
                                        $name = $meta['name'] ?? ucfirst(str_replace(['-', '_'], ' ', $featureKey));
                                        $description = $meta['description'] ?? $featureKey;
                                    @endphp
                                    <div class="flex items-center justify-between px-5 py-4 hover:bg-white transition-colors">
                                        <div class="max-w-xl">
                                            <div class="flex items-center gap-2">
                                                <label class="font-semibold text-gray-900 text-sm block">{{ $name }}</label>
                                                @if($isDefault)
                                                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-nsync-green-50 text-nsync-green-700 border border-nsync-green-100">Plan default</span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-500 mt-0.5">{{ $description }}</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="features[{{ $featureKey }}]"
                                                value="1"
                                                data-feature-category="{{ $categoryKey }}"
                                                data-plan-default="{{ $isDefault ? '1' : '0' }}"
                                                {{ $isEnabled ? 'checked' : '' }}
                                                {{ $isDefault ? 'disabled' : '' }}
                                                class="sr-only peer"
                                            >
                                            <div class="w-11 h-6 bg-gray-200 rounded-full peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-nsync-green-500 dark:bg-gray-700 peer-disabled:opacity-60 peer-disabled:cursor-not-allowed peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-nsync-green-600"></div>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex gap-4 mt-8 pt-8 border-t border-gray-200/50 dark:border-gray-700/50">
                    <button type="submit" class="px-8 py-3 bg-nsync-green-600 text-white font-bold rounded-xl hover:bg-nsync-green-700 transition-all shadow-lg hover:shadow-xl flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        Save Changes
                    </button>
                    <a href="{{ route('admin.tenants.index') }}" class="px-8 py-3 border border-gray-300 font-semibold rounded-xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all shadow-sm">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function toggleFeatureCategory(categoryKey, enable) {
        const selector = `input[data-feature-category="${categoryKey}"]`;
        const checkboxes = document.querySelectorAll(selector);

        checkboxes.forEach((checkbox) => {
            if (checkbox.disabled || checkbox.dataset.planDefault === '1') {
                return;
            }

            checkbox.checked = !!enable;
        });
    }
</script>
@endsection
