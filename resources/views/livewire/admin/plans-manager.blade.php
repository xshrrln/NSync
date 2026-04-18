@php
    $editingPlan = $editingPlan ?? null;
@endphp

<div class="min-h-screen bg-slate-50 p-8">
    <div class="mx-auto max-w-6xl">
        <div class="mb-8 flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 mb-0">Pricing Plans Manager</h1>
                <p class="text-gray-600 mb-0">Edit subscription plans directly in the central app without changing code.</p>
            </div>
            <button wire:click="addNew" class="rounded-xl bg-emerald-600 px-6 py-3 text-base font-semibold text-white shadow-lg transition hover:bg-emerald-700">
                + New Plan
            </button>
        </div>

        @if($editingPlan)
            <div class="mb-8 rounded-2xl border border-gray-200 bg-white p-8 shadow-sm">
                <div class="mb-8 flex items-center gap-4">
                    <h2 class="text-2xl font-bold text-gray-900">
                        {{ 'Edit ' . ($editingPlan->name ?? '') }}
                    </h2>

                    @if(isset($editingPlan->id))
                        <button wire:click="delete" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700">
                            Delete Plan
                        </button>
                    @endif
                </div>

                <form wire:submit="save" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Plan Name</label>
                            <input type="text" wire:model="form.name" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500" placeholder="Plan name">
                            @error('form.name') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Price Label</label>
                            <input type="text" wire:model="form.price" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500" placeholder="PHP 799/month">
                            @error('form.price') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Members Limit</label>
                            <input type="number" min="1" wire:model="form.members_limit" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500">
                            @error('form.members_limit') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Boards Limit</label>
                            <input type="number" min="1" wire:model="form.boards_limit" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500">
                            @error('form.boards_limit') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-gray-700">Storage Limit (MB)</label>
                            <input type="number" min="1" wire:model="form.storage_limit" class="w-full rounded-xl border border-gray-300 px-4 py-3 text-base focus:border-emerald-500 focus:ring-emerald-500">
                            @error('form.storage_limit') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Included Features</h3>
                                <p class="text-sm text-gray-600">Enable the capabilities included in this plan.</p>
                            </div>
                            <label class="inline-flex items-center gap-3 rounded-xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700">
                                <input type="checkbox" wire:model="form.is_active" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                Active plan
                            </label>
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                            @foreach($featuresCatalog as $feature)
                                <label class="flex items-start gap-3 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                                    <input type="checkbox" wire:model="form.features" value="{{ $feature }}" class="mt-1 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <span>{{ ucwords(str_replace('-', ' ', $feature)) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('form.features') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-200 pt-6 sm:flex-row sm:justify-end">
                        <button type="button" wire:click="addNew" class="rounded-xl border border-gray-300 px-5 py-3 text-base font-semibold text-gray-700 transition hover:bg-gray-50">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-xl bg-emerald-600 px-6 py-3 text-base font-semibold text-white transition hover:bg-emerald-700">
                            Save Plan
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
            @foreach($plans as $plan)
                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900">{{ $plan['name'] }}</h3>
                            <p class="mt-1 text-lg font-semibold text-emerald-700">{{ $plan['price'] }}</p>
                        </div>
                        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ ($plan['is_active'] ?? true) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                            {{ ($plan['is_active'] ?? true) ? 'Active' : 'Inactive' }}
                        </span>
                    </div>

                    <div class="mt-5 grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-slate-500">Members</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($plan['members_limit']) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-slate-500">Boards</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ $plan['boards_limit'] >= 999 ? 'Inf' : number_format($plan['boards_limit']) }}</p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-slate-500">Storage</p>
                            <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format($plan['storage_limit']) }} MB</p>
                        </div>
                    </div>

                    <div class="mt-5">
                        <p class="text-sm font-semibold text-gray-700">Features</p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @forelse($plan['features'] ?? [] as $feature)
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">
                                    {{ ucwords(str_replace('-', ' ', $feature)) }}
                                </span>
                            @empty
                                <span class="text-sm text-gray-500">No features selected yet.</span>
                            @endforelse
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-between gap-3">
                        <button wire:click="edit({{ $plan['id'] }})" class="rounded-xl border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">
                            Edit
                        </button>
                        <button wire:click="edit({{ $plan['id'] }})" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-black">
                            Manage
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>



