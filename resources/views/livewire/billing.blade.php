<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\Board;
use App\Models\Task;
use Illuminate\Support\Carbon;

new class extends Component {
    public $plan = 'free';
    public $cardHolder = '';
    public $cardNumber = '';
    public $cardExpiry = '';
    public $billingCountry = '';
    public $cardLastFour = null;
    public $cardExpiryOnFile = null;
    public $processing = false;

    public function mount() {
        $tenant = app('currentTenant');
        $this->plan = $tenant?->plan ?? 'free';
        $rawBilling = $tenant?->billing_data;
        $billing = match (true) {
            is_array($rawBilling) => $rawBilling,
            is_string($rawBilling) && $rawBilling !== '' => json_decode($rawBilling, true) ?: [],
            default => [],
        };

        $this->cardHolder = $billing['cardholder_name'] ?? Auth::user()->name;
        $this->billingCountry = $billing['billing_country'] ?? '';
        $this->cardLastFour = $billing['card_last_four'] ?? null;
        $this->cardExpiryOnFile = $billing['card_expiry'] ?? null;
    }

    public function with() {
        $tenant = app('currentTenant');
        $plans = collect(config('plans'));

        $canViewReporting = $tenant ? ($tenant->hasFeature('advanced-reporting') || Auth::user()->hasRole('Team Supervisor')) : false;
        $canExportAudit = $tenant ? $tenant->hasFeature('audit-export') : false;

        return [
            'tenant' => $tenant,
            'plans' => $plans,
            'canManageBilling' => Auth::user()->hasRole('Team Supervisor'),
            'canViewReporting' => $canViewReporting,
            'canExportAudit' => $canExportAudit,
            'usage' => [
                'boards' => $tenant ? Board::count() : 0,
                'tasks' => $tenant ? Task::count() : 0,
            ],
            'renewsOn' => $tenant && $tenant->due_date ? Carbon::parse($tenant->due_date)->format('M d, Y') : null,
        ];
    }

    public function updatePlan($selectedPlan) {
        $this->authorizeAccess();

        if (! in_array($selectedPlan, ['free', 'standard', 'pro'], true)) {
            return;
        }

        $tenant = app('currentTenant');
        if (! $tenant) {
            return;
        }

        $tenant->update([
            'plan' => $selectedPlan,
            'start_date' => now(),
            'due_date' => $selectedPlan === 'free' ? now()->addDays(14) : now()->addDays(30),
        ]);

        $this->plan = $selectedPlan;
        $this->dispatch('notify', 'Plan updated successfully');
    }

    public function updateBilling() {
        $this->authorizeAccess();

        $this->validate([
            'cardHolder' => ['required', 'string', 'max:255'],
            'cardNumber' => ['required', 'regex:/^\d{16}$/'],
            'cardExpiry' => ['required', 'regex:/^(0[1-9]|1[0-2])\/\d{2}$/'],
            'billingCountry' => ['required', 'string', 'max:120'],
        ]);

        $tenant = app('currentTenant');
        if (! $tenant) {
            return;
        }

        $billingData = [
            'card_last_four' => substr($this->cardNumber, -4),
            'cardholder_name' => $this->cardHolder,
            'billing_country' => $this->billingCountry,
            'card_expiry' => $this->cardExpiry,
        ];

        $tenant->update(['billing_data' => $billingData]);

        $this->cardLastFour = $billingData['card_last_four'];
        $this->cardExpiryOnFile = $billingData['card_expiry'];
        $this->cardNumber = '';
        $this->processing = false;

        $this->dispatch('notify', 'Billing details saved securely');
    }

    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()->hasRole('Team Supervisor'), 403, 'Only workspace supervisors can manage billing.');
    }
}; ?>

<div class="bg-white min-h-screen">
    <div class="w-full text-left">
        @php
            $currentPlanMeta = $plans->get(strtolower($tenant->plan ?? 'free'), []);
            $membersLimit = $currentPlanMeta['members_limit'] ?? null;
            $boardsLimit = $currentPlanMeta['boards_limit'] ?? null;
            $storageLimit = $currentPlanMeta['storage_limit'] ?? null;
            $includedFeatures = $tenant?->enabledFeatures() ?? ($currentPlanMeta['features'] ?? []);
            $featureMeta = collect(config('features.categories', []))
                ->pluck('features')
                ->flatten(1);
            $displayOverrides = [
                'basic-kanban' => ($tenant?->plan === 'pro') ? 'Advanced Kanban Workspace' : null,
                'basic-analytics' => ($tenant?->plan === 'pro') ? 'Advanced Analytics Suite' : null,
            ];
        @endphp

        <div class="mb-8 flex items-center justify-between">
            <div>
                <span class="mb-1 block text-[10px] font-bold uppercase tracking-[0.2em] text-nsync-green-600">Workspace Billing</span>
                <h1 class="text-2xl font-bold text-gray-900 mb-0">Plan, Usage, and Payment Details</h1>
                <p class="text-gray-600 mb-0">View your workspace subscription, included limits, and billing information.</p>
            </div>
            <div>
                <span class="rounded-full border border-nsync-green-100 bg-nsync-green-50 px-3 py-1 text-[10px] font-bold uppercase text-nsync-green-600">Tenant</span>
            </div>
        </div>

        <div class="space-y-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-5 flex items-start justify-between">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Current Plan</p>
                            <h2 class="mt-2 text-2xl font-black text-slate-900">{{ strtoupper($tenant->plan ?? 'free') }} Plan</h2>
                            <p class="mt-1 text-sm font-semibold text-nsync-green-700">{{ $currentPlanMeta['price'] ?? 'Free Forever' }}</p>
                        </div>
                        <span class="rounded-full bg-green-50 px-3 py-1 text-[10px] font-bold uppercase text-green-700">Active</span>
                    </div>

                    <div class="space-y-3 text-sm text-slate-600">
                        <div class="flex items-center justify-between">
                            <span>Renewal</span>
                            <span class="font-semibold text-slate-900">{{ $renewsOn ?? 'Not scheduled' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span>Billing Manager</span>
                            <span class="font-semibold text-slate-900">{{ $canManageBilling ? 'Workspace Supervisor' : 'Read only' }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Usage</p>
                        <h2 class="mt-2 text-lg font-bold text-slate-900">Workspace Limits</h2>
                    </div>

                    <div class="space-y-5">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-600">Boards</span>
                                <span class="font-semibold text-slate-900">{{ number_format($usage['boards']) }} / {{ $boardsLimit >= 999 ? 'Unlimited' : number_format($boardsLimit ?? 0) }}</span>
                            </div>
                            @if(($boardsLimit ?? 0) > 0 && $boardsLimit < 999)
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-nsync-green-600" style="width: {{ min(100, ($usage['boards'] / max(1, $boardsLimit)) * 100) }}%"></div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-600">Members</span>
                                <span class="font-semibold text-slate-900">{{ number_format($tenant->member_count) }} / {{ $membersLimit >= 999 ? 'Unlimited' : number_format($membersLimit ?? 0) }}</span>
                            </div>
                            @if(($membersLimit ?? 0) > 0 && $membersLimit < 999)
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-nsync-green-600" style="width: {{ min(100, ($tenant->member_count / max(1, $membersLimit)) * 100) }}%"></div>
                                </div>
                            @endif
                        </div>

                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-slate-600">Storage</span>
                                <span class="font-semibold text-slate-900">{{ number_format($tenant->storage_used, 1) }} MB / {{ $storageLimit >= 999999 ? 'Unlimited' : number_format($storageLimit ?? 0) . ' MB' }}</span>
                            </div>
                            @if(($storageLimit ?? 0) > 0 && $storageLimit < 999999)
                                <div class="h-2 rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-nsync-green-600" style="width: {{ min(100, ($tenant->storage_used / max(1, $storageLimit)) * 100) }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                    <div class="mb-5">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Included Features</p>
                        <h2 class="mt-2 text-lg font-bold text-slate-900">What Your Plan Includes</h2>
                    </div>

                    <div class="space-y-2 text-sm text-slate-700">
                        @forelse($includedFeatures as $feature)
                            <div class="flex items-start gap-3 rounded-xl bg-slate-50 px-4 py-3">
                                <span class="mt-0.5 text-green-600">&#10003;</span>
                                <span>{{ $displayOverrides[$feature] ?? ($featureMeta[$feature]['name'] ?? str($feature)->replace('-', ' ')->title()) }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No feature list available for this plan yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.4fr,1fr]">
                <div class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm">
                    <div class="border-b border-slate-50 px-8 py-6">
                        <h2 class="text-lg font-bold text-slate-900">Billing Method</h2>
                        <p class="mt-1 text-sm text-slate-500">Keep your workspace payment details up to date.</p>
                    </div>

                    <div class="px-8 py-6">
                        @if($canManageBilling)
                            <form wire:submit="updateBilling" class="space-y-5">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Cardholder Name</label>
                                    <input type="text" wire:model="cardHolder" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500">
                                    @error('cardHolder') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Card Number</label>
                                    <input type="text" wire:model="cardNumber" placeholder="1234123412341234" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500">
                                    @error('cardNumber') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Expiry</label>
                                        <input type="text" wire:model="cardExpiry" placeholder="MM/YY" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500">
                                        @error('cardExpiry') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div>
                                        <label class="mb-2 block text-sm font-semibold text-slate-700">Billing Country</label>
                                        <input type="text" wire:model="billingCountry" class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500">
                                        @error('billingCountry') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <div class="pt-2">
                                    <button type="submit" class="rounded-xl bg-nsync-green-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-nsync-green-700">Save Billing Details</button>
                                </div>
                            </form>
                        @else
                            <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4 text-sm text-amber-800">Only workspace supervisors can update billing details. Contact your workspace admin if you need changes.</div>
                        @endif
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-100 bg-white p-8 shadow-sm">
                        <h2 class="text-lg font-bold text-slate-900">Payment Summary</h2>
                        <div class="mt-5 space-y-3 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Card on file</span>
                                <span class="font-semibold text-slate-900">{{ $cardLastFour ? '**** ' . $cardLastFour : 'No card saved' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Expiry</span>
                                <span class="font-semibold text-slate-900">{{ $cardExpiryOnFile ?: 'Not set' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600">Tasks in workspace</span>
                                <span class="font-semibold text-slate-900">{{ number_format($usage['tasks']) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-nsync-green-100 bg-nsync-green-50/60 p-8">
                        <h2 class="text-lg font-bold text-slate-900">Need a Different Plan?</h2>
                        <p class="mt-2 text-sm text-slate-600">For upgrades, plan corrections, or billing concerns, contact your platform administrator or support team.</p>
                    </div>

                    @if($canViewReporting)
                        <a href="{{ route('reports') }}" class="block rounded-2xl border border-slate-100 bg-white p-8 shadow-sm transition hover:shadow-md">
                            <h2 class="text-lg font-bold text-slate-900">Reports & Exports</h2>
                            <p class="mt-2 text-sm text-slate-600">Reporting has moved to a dedicated page. Open workspace reports to view analytics and export CSV files.</p>
                            <span class="mt-4 inline-flex items-center rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Open Reports</span>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>



