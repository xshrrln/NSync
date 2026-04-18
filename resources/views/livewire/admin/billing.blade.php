<?php

use Livewire\Volt\Component;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

new class extends Component {
    public $editingPlanId = null;
    public $newPlanOpen = false;
    public $form = [
        'name' => '',
        'slug' => '',
        'price' => '',
        'members_limit' => 5,
        'boards_limit' => 3,
        'storage_limit' => 50,
        'features' => [],
        'is_active' => true,
    ];

    public function updatedFormPrice($value): void
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            $this->form['price'] = '';
            return;
        }

        $numeric = preg_replace('/[^0-9.]/', '', $raw) ?? '';
        if ($numeric === '') {
            $this->form['price'] = '';
            return;
        }

        $normalized = 'PHP ' . $numeric;
        if (($this->form['price'] ?? '') !== $normalized) {
            $this->form['price'] = $normalized;
        }
    }

    private function featureCategories(): array
    {
        return collect(config('features.categories', []))
            ->map(fn ($category) => [
                'label' => $category['label'] ?? 'Features',
                'features' => $category['features'] ?? [],
            ])
            ->values()
            ->all();
    }

    public function with(): array
    {
        $featuresCatalog = $this->featureCategories();
        $hasPlansTable = false;
        $hasTenantsTable = false;

        try {
            $hasPlansTable = Schema::hasTable('plans');
            $hasTenantsTable = Schema::hasTable('tenants');
        } catch (\Throwable $e) {
            report($e);
        }

        $plans = collect();
        $usage = [
            'tenants' => 0,
            'paid' => 0,
            'free' => 0,
        ];
        $recentTenants = collect();

        try {
            if ($hasPlansTable) {
                $plans = Plan::query()->where('is_active', true)->orderBy('id')->get();
            }

            if ($hasTenantsTable) {
                $usage = [
                    'tenants' => Tenant::count(),
                    'paid' => Tenant::whereNotNull('plan')->where('plan', '!=', 'free')->count(),
                    'free' => Tenant::where('plan', 'free')->orWhereNull('plan')->count(),
                ];
                $recentTenants = Tenant::latest('created_at')->limit(5)->get(['id', 'name', 'plan', 'status', 'created_at']);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'plans' => $plans,
            'usage' => $usage,
            'recentTenants' => $recentTenants,
            'featuresCatalog' => $featuresCatalog,
        ];
    }

    public function toggleFeatureCategory(int $categoryIndex, bool $enable): void
    {
        $category = $this->featureCategories()[$categoryIndex] ?? null;
        if (! $category) {
            return;
        }

        $categoryFeatureKeys = array_keys($category['features'] ?? []);
        $selected = collect($this->form['features'] ?? [])
            ->filter(fn ($feature) => is_string($feature))
            ->values()
            ->all();

        if ($enable) {
            $selected = array_values(array_unique(array_merge($selected, $categoryFeatureKeys)));
        } else {
            $selected = array_values(array_diff($selected, $categoryFeatureKeys));
        }

        $this->form['features'] = $selected;
    }

    public function edit($id): void
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return;
        }

        $this->editingPlanId = $id;
        $this->form = $plan->toArray();
        $this->form['features'] = $plan->features ?? [];
    }

    public function cancelEdit(): void
    {
        $this->editingPlanId = null;
        $this->resetForm();
    }

    public function saveEdit(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:50',
            'form.price' => 'required|string|max:100',
            'form.members_limit' => 'required|integer|min:1',
            'form.boards_limit' => 'required|integer|min:1',
            'form.storage_limit' => 'required|integer|min:1',
            'form.is_active' => 'boolean',
            'form.features' => 'array',
        ]);

        $plan = Plan::find($this->editingPlanId);

        if ($plan) {
            $plan->update(array_merge($this->form, [
                'slug' => strtolower(str_replace(' ', '-', trim($this->form['name']))),
            ]));
        }

        $this->cancelEdit();
        $this->dispatch('notify', message: 'Plan updated!', type: 'success');
    }

    public function openNewPlan(): void
    {
        $this->newPlanOpen = true;
        $this->resetForm();
    }

    public function closeNewPlan(): void
    {
        $this->newPlanOpen = false;
        $this->resetForm();
    }

    public function saveNewPlan(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:50',
            'form.price' => 'required|string|max:100',
            'form.members_limit' => 'required|integer|min:1',
            'form.boards_limit' => 'required|integer|min:1',
            'form.storage_limit' => 'required|integer|min:1',
            'form.is_active' => 'boolean',
            'form.features' => 'array',
        ]);

        Plan::create(array_merge($this->form, [
            'slug' => strtolower(str_replace(' ', '-', trim($this->form['name']))),
        ]));

        $this->closeNewPlan();
        $this->dispatch('notify', message: 'New plan created!', type: 'success');
    }

    public function archivePlan($id): void
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return;
        }

        $name = $plan->name;
        $plan->update(['is_active' => false]);

        if ($this->editingPlanId === $plan->id) {
            $this->cancelEdit();
        }

        $this->dispatch('notify', message: "Plan '{$name}' archived!", type: 'success');
    }

    public function restorePlan($id): void
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return;
        }

        $plan->update(['is_active' => true]);
        $this->dispatch('notify', message: "Plan '{$plan->name}' restored!", type: 'success');
    }

    private function resetForm(): void
    {
        $this->form = [
            'name' => '',
            'slug' => '',
            'price' => '',
            'members_limit' => 5,
            'boards_limit' => 3,
            'storage_limit' => 50,
            'features' => [],
            'is_active' => true,
        ];
    }
}; ?>

<div>
    <div class="pt-4 px-8 pb-8 lg:pt-6 lg:px-10">
        <div class="mb-10">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-0">Platform Billing Overview</h1>
                    <p class="text-gray-600 mb-0">Global platform subscription management and tenant billing analytics.</p>
                </div>
                <span class="rounded-full border border-nsync-green-100 bg-nsync-green-50 px-3 py-1 text-sm font-bold uppercase text-nsync-green-600">
                    Central
                </span>
            </div>
        </div>

        <div class="mb-8 grid grid-cols-1 gap-6 md:grid-cols-3">
            <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                <span class="mb-4 block text-sm font-bold uppercase tracking-widest text-slate-400">Total Tenants</span>
                <span class="text-4xl font-black text-slate-900">{{ number_format($usage['tenants']) }}</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                <span class="mb-4 block text-sm font-bold uppercase tracking-widest text-emerald-500">Paid Plans</span>
                <span class="text-4xl font-black text-slate-900">{{ number_format($usage['paid']) }}</span>
            </div>

            <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                <span class="mb-4 block text-sm font-bold uppercase tracking-widest text-slate-400">Free Plans</span>
                <span class="text-4xl font-black text-slate-900">{{ number_format($usage['free']) }}</span>
            </div>
        </div>

        <div class="mb-8 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-50 px-8 py-6">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Available Plans</h2>
                    <p class="text-base text-slate-500">Live active plans from database</p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.archive') }}" class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                        View Archive
                    </a>
                    <button wire:click="openNewPlan" class="rounded-xl bg-nsync-green-600 px-6 py-2 text-sm font-bold text-white shadow-sm transition hover:bg-nsync-green-700">
                        + Add Plan
                    </button>
                </div>
            </div>

            @php
                $planCount = $plans->count();
                $planGridClasses = match (true) {
                    $planCount >= 4 => 'xl:grid-cols-4',
                    $planCount === 3 => 'xl:grid-cols-3',
                    $planCount === 2 => 'xl:grid-cols-2',
                    default => 'xl:grid-cols-1',
                };
            @endphp

            <div class="grid grid-cols-1 gap-6 p-8 md:grid-cols-2 {{ $planGridClasses }}">
                @forelse($plans as $plan)
                    @if($this->editingPlanId === $plan->id)
                        <div class="rounded-xl border-2 border-nsync-green-200 bg-nsync-green-50 p-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h4 class="text-lg font-bold text-slate-900">Edit {{ $this->form['name'] }}</h4>
                                <div class="flex gap-2">
                                    <button wire:click="saveEdit" class="rounded-lg bg-nsync-green-600 px-4 py-1.5 text-sm font-bold text-white hover:bg-nsync-green-700">
                                        Save
                                    </button>
                                    @if($editingPlanId && ($form['is_active'] ?? true))
                                        <button wire:click="archivePlan({{ $editingPlanId }})" type="button" class="rounded-lg bg-amber-500 px-4 py-1.5 text-sm font-bold text-white hover:bg-amber-600">
                                            Archive
                                        </button>
                                    @elseif($editingPlanId)
                                        <button wire:click="restorePlan({{ $editingPlanId }})" type="button" class="rounded-lg bg-sky-600 px-4 py-1.5 text-sm font-bold text-white hover:bg-sky-700">
                                            Restore
                                        </button>
                                    @endif
                                    <button wire:click="cancelEdit" class="rounded-lg bg-slate-200 px-4 py-1.5 text-sm font-bold text-slate-700 hover:bg-slate-300">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Plan Name</label>
                                    <input type="text" wire:model="form.name" placeholder="Enter plan name" class="w-full rounded-lg border border-slate-200 p-3 text-base focus:ring-2 focus:ring-nsync-green-500">
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Price</label>
                                    <input type="text" wire:model.live.debounce.250ms="form.price" placeholder="PHP" class="w-full rounded-lg border border-slate-200 p-3 text-base focus:ring-2 focus:ring-nsync-green-500">
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Member Limit</label>
                                        <input type="number" wire:model="form.members_limit" placeholder="Enter member limit" class="w-full rounded-lg border border-slate-200 p-3 text-base">
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Board Limit</label>
                                        <input type="number" wire:model="form.boards_limit" placeholder="Enter board limit" class="w-full rounded-lg border border-slate-200 p-3 text-base">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Storage Limit (MB)</label>
                                        <input type="number" wire:model="form.storage_limit" placeholder="Enter storage limit" class="w-full rounded-lg border border-slate-200 p-3 text-base">
                                    </div>
                                </div>
                                <label class="flex items-center rounded-lg border border-slate-200 p-3">
                                    <input type="checkbox" wire:model="form.is_active" class="rounded text-nsync-green-600">
                                    <span class="ml-3 text-base font-semibold">Active Plan</span>
                                </label>
                                <div class="rounded-xl border border-slate-200 bg-white p-4">
                                    <p class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Included Features</p>
                                    <div class="space-y-4">
                                        @foreach($featuresCatalog as $categoryIndex => $category)
                                            <div>
                                                <div class="mb-2 flex items-center justify-between">
                                                    <p class="text-sm font-semibold text-slate-700">{{ $category['label'] }}</p>
                                                    <div class="flex items-center gap-2">
                                                        <button type="button" wire:click="toggleFeatureCategory({{ $categoryIndex }}, true)" class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100">Select All</button>
                                                        <button type="button" wire:click="toggleFeatureCategory({{ $categoryIndex }}, false)" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                                    </div>
                                                </div>
                                                <div class="space-y-2">
                                                    @foreach($category['features'] as $featureKey => $feature)
                                                        <label class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                                                            <input type="checkbox" wire:model="form.features" value="{{ $featureKey }}" class="mt-1 rounded text-nsync-green-600">
                                                            <span>
                                                                <span class="block text-sm font-semibold text-slate-800">{{ $feature['name'] }}</span>
                                                                <span class="block text-xs text-slate-500">{{ $feature['description'] }}</span>
                                                            </span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex h-full cursor-pointer flex-col rounded-xl border border-slate-100 bg-slate-50/50 p-6 transition hover:shadow-md" wire:click="edit({{ $plan->id }})">
                            <div class="mb-2 flex items-center justify-between">
                                <h4 class="text-base font-bold text-slate-900">{{ $plan->name }}</h4>
                                <div class="flex items-center gap-2">
                                    <button class="rounded-lg bg-amber-500 px-3 py-1 text-xs font-bold text-white hover:bg-amber-600" onclick="event.stopPropagation(); @this.call('archivePlan', {{ $plan->id }})">
                                        Archive
                                    </button>
                                </div>
                            </div>
                            <div class="mb-4 text-2xl font-black text-slate-900">{{ $plan->price }}</div>
                            <ul class="mb-6 space-y-1 text-base text-slate-600">
                                <li>{{ number_format($plan->members_limit) }} members</li>
                                <li>{{ number_format($plan->boards_limit) }} boards</li>
                                <li>{{ number_format($plan->storage_limit) }} MB storage</li>
                            </ul>
                            <div class="mb-6">
                                <p class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Included Features</p>
                                <div class="space-y-2">
                                    @forelse($plan->features ?? [] as $featureKey)
                                        @php
                                            $featureMeta = collect($featuresCatalog)
                                                ->pluck('features')
                                                ->flatten(1)
                                                ->get($featureKey);
                                        @endphp
                                        <div class="flex items-start gap-2 text-sm text-slate-700">
                                            <span class="mt-0.5 text-emerald-600">✓</span>
                                            <div>
                                                <span class="block font-semibold">{{ $featureMeta['name'] ?? ucwords(str_replace('-', ' ', $featureKey)) }}</span>
                                                @if(!empty($featureMeta['description']))
                                                    <span class="block text-xs text-slate-500">{{ $featureMeta['description'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-sm text-slate-500">No features selected.</p>
                                    @endforelse
                                </div>
                            </div>
                            <div class="mt-auto pt-2">
                                <span class="inline-flex rounded-full bg-white px-3 py-1 text-sm font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200">
                                    {{ $plan->slug }}
                                </span>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="col-span-full py-12 text-center text-slate-500">
                        <p>No active plans found. <button wire:click="openNewPlan" class="font-semibold text-nsync-green-600 hover:underline">Create your first plan →</button></p>
                    </div>
                @endforelse
            </div>
        </div>

        @if($this->newPlanOpen)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 p-4 sm:p-6" x-data x-show="$wire.newPlanOpen" x-transition>
                <div class="flex min-h-full items-start justify-center py-4 sm:py-8">
                    <div class="flex max-h-[calc(100vh-2rem)] w-full max-w-2xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:max-h-[calc(100vh-4rem)]">
                    <div class="flex items-center justify-between border-b border-slate-100 px-8 py-6">
                        <h3 class="text-2xl font-bold text-slate-900">Add New Plan</h3>
                        <button wire:click="closeNewPlan" class="text-slate-400 hover:text-slate-600">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <div class="space-y-4 overflow-y-auto px-8 py-6">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Plan Name</label>
                            <input type="text" wire:model="form.name" placeholder="Enter plan name" class="w-full rounded-xl border border-slate-200 p-4 text-lg font-bold focus:ring-2 focus:ring-nsync-green-500">
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Price</label>
                            <input type="text" wire:model.live.debounce.250ms="form.price" placeholder="PHP" class="w-full rounded-xl border border-slate-200 p-4 text-2xl font-black focus:ring-2 focus:ring-nsync-green-500">
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Members</label>
                                <input type="number" wire:model="form.members_limit" placeholder="Enter member limit" class="w-full rounded-lg border border-slate-200 p-3 text-center text-base">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Boards</label>
                                <input type="number" wire:model="form.boards_limit" placeholder="Enter board limit" class="w-full rounded-lg border border-slate-200 p-3 text-center text-base">
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Storage (MB)</label>
                                <input type="number" wire:model="form.storage_limit" placeholder="Enter storage limit" class="w-full rounded-lg border border-slate-200 p-3 text-center text-base">
                            </div>
                        </div>
                        <label class="flex items-center rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <input type="checkbox" wire:model="form.is_active" class="h-5 w-5 rounded text-nsync-green-600">
                            <span class="ml-3 text-lg font-bold">Active Plan</span>
                        </label>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Included Features</p>
                            <div class="space-y-4 max-h-72 overflow-y-auto pr-1">
                                @foreach($featuresCatalog as $categoryIndex => $category)
                                    <div>
                                        <div class="mb-2 flex items-center justify-between">
                                            <p class="text-sm font-semibold text-slate-700">{{ $category['label'] }}</p>
                                            <div class="flex items-center gap-2">
                                                <button type="button" wire:click="toggleFeatureCategory({{ $categoryIndex }}, true)" class="rounded-md border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100">Select All</button>
                                                <button type="button" wire:click="toggleFeatureCategory({{ $categoryIndex }}, false)" class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">Clear</button>
                                            </div>
                                        </div>
                                        <div class="space-y-2">
                                            @foreach($category['features'] as $featureKey => $feature)
                                                <label class="flex items-start gap-3 rounded-lg border border-slate-100 bg-white px-3 py-2">
                                                    <input type="checkbox" wire:model="form.features" value="{{ $featureKey }}" class="mt-1 rounded text-nsync-green-600">
                                                    <span>
                                                        <span class="block text-sm font-semibold text-slate-800">{{ $feature['name'] }}</span>
                                                        <span class="block text-xs text-slate-500">{{ $feature['description'] }}</span>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="border-t border-slate-100 px-8 py-5">
                        <button wire:click="saveNewPlan" class="w-full rounded-xl bg-nsync-green-600 py-4 text-lg font-bold text-white shadow-lg transition hover:bg-nsync-green-700">
                            Create Plan
                        </button>
                    </div>
                </div>
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
            <div class="border-b border-slate-50 px-8 py-6">
                <h2 class="text-lg font-bold text-slate-900">Recent Tenants</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-8 py-4 text-left text-sm font-bold uppercase tracking-wide text-slate-400">Tenant</th>
                            <th class="px-4 py-4 text-left text-sm font-bold uppercase tracking-wide text-slate-400">Plan</th>
                            <th class="px-4 py-4 text-left text-sm font-bold uppercase tracking-wide text-slate-400">Status</th>
                            <th class="px-4 py-4 text-left text-sm font-bold uppercase tracking-wide text-slate-400">Members</th>
                            <th class="px-4 py-4 text-left text-sm font-bold uppercase tracking-wide text-slate-400">Created</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($recentTenants as $tenant)
                        <tr class="hover:bg-slate-50">
                            <td class="px-8 py-4 font-medium text-slate-900">
                                {{ $tenant->name }}
                            </td>
                            <td class="px-4 py-4">
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-sm font-bold text-slate-700">
                                    {{ ucfirst($tenant->plan ?? 'free') }}
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                @switch($tenant->status)
                                    @case('active')
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 text-sm font-bold text-emerald-700">Active</span>
                                        @break
                                    @case('pending')
                                        <span class="rounded-full bg-amber-100 px-2 py-1 text-sm font-bold text-amber-700">Pending</span>
                                        @break
                                    @default
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-sm font-bold text-slate-700">{{ ucfirst($tenant->status) }}</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-4 text-base text-slate-600">
                                {{ $tenant->users()->count() }}
                            </td>
                            <td class="px-4 py-4 text-base text-slate-500">
                                {{ $tenant->created_at->diffForHumans() }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-8 py-12 text-center text-sm italic text-slate-400">No recent tenant activity found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>




