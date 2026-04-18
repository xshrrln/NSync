<?php

use Livewire\Volt\Component;
use App\Models\Plan;

new class extends Component {
    public function with(): array
    {
        return [
            'archivedPlans' => Plan::query()
                ->where('is_active', false)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get(),
        ];
    }

    public function restorePlan(int $id): void
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return;
        }

        $plan->update(['is_active' => true]);
        $this->dispatch('notify', message: "Plan '{$plan->name}' restored!", type: 'success');
    }
}; ?>

<div class="space-y-8">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 mb-0">Archive</h1>
            <p class="text-gray-600 mb-0">Archived records stay here instead of being deleted, so the central app keeps history.</p>
        </div>
        <a href="{{ route('admin.billing') }}" class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
            Back to Billing
        </a>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
        <div class="border-b border-slate-50 px-8 py-6">
            <h2 class="text-lg font-bold text-slate-900">Archived Plans</h2>
            <p class="mt-1 text-base text-slate-500">Plans removed from the active billing page are stored here.</p>
        </div>

        @if($archivedPlans->isEmpty())
            <div class="px-8 py-16 text-center">
                <p class="text-base text-slate-500">No archived plans yet.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 p-8 md:grid-cols-2 xl:grid-cols-3">
                @foreach($archivedPlans as $plan)
                    <div class="flex h-full flex-col rounded-xl border border-amber-200 bg-amber-50/60 p-6">
                        <div class="mb-3 flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900">{{ $plan->name }}</h3>
                                <p class="mt-1 text-xl font-black text-slate-900">{{ $plan->price }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold uppercase text-amber-800">Archived</span>
                        </div>

                        <ul class="mb-5 space-y-1 text-base text-slate-600">
                            <li>{{ number_format($plan->members_limit) }} members</li>
                            <li>{{ number_format($plan->boards_limit) }} boards</li>
                            <li>{{ number_format($plan->storage_limit) }} MB storage</li>
                        </ul>

                        <div class="mb-5">
                            <p class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500">Included Features</p>
                            <div class="space-y-2">
                                @forelse($plan->features ?? [] as $featureKey)
                                    <div class="flex items-start gap-2 text-sm text-slate-700">
                                        <span class="mt-0.5 text-emerald-600">✓</span>
                                        <span class="font-semibold">{{ ucwords(str_replace('-', ' ', $featureKey)) }}</span>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No features selected.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="mt-auto flex items-center justify-between gap-3 border-t border-amber-200 pt-4">
                            <span class="inline-flex rounded-full bg-white px-3 py-1 text-sm font-semibold text-slate-600 shadow-sm ring-1 ring-slate-200">
                                {{ $plan->slug }}
                            </span>
                            <button wire:click="restorePlan({{ $plan->id }})" class="rounded-lg bg-sky-600 px-4 py-2 text-sm font-bold text-white transition hover:bg-sky-700">
                                Restore
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>



