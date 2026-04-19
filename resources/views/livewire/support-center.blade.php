<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Component;

new class extends Component {
    public string $subject = '';
    public string $category = 'general';
    public string $priority = 'normal';
    public string $message = '';
    public string $replyMessage = '';
    public ?int $activeTicketId = null;
    public string $statusFilter = 'all';

    public function with(): array
    {
        $tenant = app('currentTenant');
        abort_unless($tenant, 404);

        if (! $this->supportTablesReady()) {
            return [
                'tickets' => collect(),
                'activeTicket' => null,
                'messages' => collect(),
                'supportUnavailable' => true,
                'statusCounts' => [
                    'open' => 0,
                    'in_progress' => 0,
                    'waiting_customer' => 0,
                    'resolved' => 0,
                    'closed' => 0,
                ],
            ];
        }

        $ticketsQuery = SupportTicket::query()
            ->where('tenant_id', $tenant->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($this->statusFilter !== 'all') {
            $ticketsQuery->where('status', $this->statusFilter);
        }

        $tickets = $ticketsQuery->get();
        $activeTicket = null;

        if ($this->activeTicketId) {
            $activeTicket = $tickets->firstWhere('id', $this->activeTicketId)
                ?? SupportTicket::query()->where('tenant_id', $tenant->id)->find($this->activeTicketId);
        }

        if (! $activeTicket && $tickets->isNotEmpty()) {
            $activeTicket = $tickets->first();
            $this->activeTicketId = $activeTicket->id;
        }

        return [
            'tickets' => $tickets,
            'activeTicket' => $activeTicket,
            'messages' => $activeTicket
                ? SupportTicketMessage::query()
                    ->where('support_ticket_id', $activeTicket->id)
                    ->orderBy('created_at')
                    ->get()
                : collect(),
            'supportUnavailable' => false,
            'statusCounts' => [
                'open' => SupportTicket::where('tenant_id', $tenant->id)->where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('tenant_id', $tenant->id)->where('status', 'in_progress')->count(),
                'waiting_customer' => SupportTicket::where('tenant_id', $tenant->id)->where('status', 'waiting_customer')->count(),
                'resolved' => SupportTicket::where('tenant_id', $tenant->id)->where('status', 'resolved')->count(),
                'closed' => SupportTicket::where('tenant_id', $tenant->id)->where('status', 'closed')->count(),
            ],
        ];
    }

    public function createTicket(): void
    {
        if (! $this->supportTablesReady()) {
            $this->dispatch('notify', message: 'Support is not initialized yet. Please run database migrations first.', type: 'error');
            return;
        }

        $this->validate([
            'subject' => ['required', 'string', 'min:5', 'max:180'],
            'category' => ['required', 'in:general,billing,technical,account,feature-request'],
            'priority' => ['required', 'in:low,normal,high,urgent'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $tenant = app('currentTenant');
        $user = Auth::user();

        DB::transaction(function () use ($tenant, $user): void {
            $ticket = SupportTicket::create([
                'tenant_id' => $tenant->id,
                'requester_user_id' => $user?->id,
                'requester_name' => $user?->name,
                'requester_email' => $user?->email,
                'subject' => trim($this->subject),
                'category' => $this->category,
                'priority' => $this->priority,
                'status' => 'open',
                'last_message_at' => now(),
            ]);

            SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'tenant_id' => $tenant->id,
                'author_type' => 'tenant',
                'author_name' => $user?->name ?? 'Tenant User',
                'author_email' => $user?->email,
                'message' => trim($this->message),
            ]);

            $this->activeTicketId = $ticket->id;
        });

        $this->reset(['subject', 'message']);
        $this->category = 'general';
        $this->priority = 'normal';
        $this->dispatch('notify', message: 'Support ticket submitted successfully.', type: 'success');
    }

    public function selectTicket(int $ticketId): void
    {
        $this->activeTicketId = $ticketId;
        $this->reset('replyMessage');
    }

    public function sendReply(): void
    {
        if (! $this->supportTablesReady()) {
            $this->dispatch('notify', message: 'Support is not initialized yet. Please run database migrations first.', type: 'error');
            return;
        }

        $this->validate([
            'replyMessage' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        $tenant = app('currentTenant');
        $user = Auth::user();
        $ticket = SupportTicket::query()
            ->where('tenant_id', $tenant->id)
            ->find($this->activeTicketId);

        if (! $ticket) {
            $this->dispatch('notify', message: 'Selected support ticket was not found.', type: 'error');
            return;
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'tenant_id' => $tenant->id,
            'author_type' => 'tenant',
            'author_name' => $user?->name ?? 'Tenant User',
            'author_email' => $user?->email,
            'message' => trim($this->replyMessage),
        ]);

        $ticket->update([
            'status' => 'open',
            'last_message_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        $this->reset('replyMessage');
        $this->dispatch('notify', message: 'Reply sent to support.', type: 'success');
    }

    public function markResolved(int $ticketId): void
    {
        if (! $this->supportTablesReady()) {
            return;
        }

        $tenant = app('currentTenant');
        $ticket = SupportTicket::query()->where('tenant_id', $tenant->id)->find($ticketId);

        if (! $ticket) {
            return;
        }

        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'closed_at' => null,
        ]);

        $this->dispatch('notify', message: 'Ticket marked as resolved.', type: 'success');
    }

    public function reopenTicket(int $ticketId): void
    {
        if (! $this->supportTablesReady()) {
            return;
        }

        $tenant = app('currentTenant');
        $ticket = SupportTicket::query()->where('tenant_id', $tenant->id)->find($ticketId);

        if (! $ticket) {
            return;
        }

        $ticket->update([
            'status' => 'open',
            'resolved_at' => null,
            'closed_at' => null,
            'last_message_at' => now(),
        ]);

        $this->dispatch('notify', message: 'Ticket reopened.', type: 'success');
    }

    private function supportTablesReady(): bool
    {
        return Schema::hasTable('support_tickets') && Schema::hasTable('support_ticket_messages');
    }
};
?>

<div class="min-h-screen bg-white">
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between py-4 min-h-[116px]">
                <div>
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Support Center</h1>
                    <p class="text-gray-600 mb-0">Track tickets, send updates, and resolve support concerns.</p>
                </div>
                <div>
                    <span class="rounded-full border px-3 py-1 text-[10px] font-bold uppercase" style="border-color: color-mix(in srgb, var(--tenant-primary) 25%, white 75%); background-color: color-mix(in srgb, var(--tenant-primary) 10%, white 90%); color: color-mix(in srgb, var(--tenant-primary) 80%, black 20%);">Support</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white px-8 py-8 shadow-lg" style="border-color: color-mix(in srgb, var(--tenant-primary) 20%, white 80%);">
            <h2 class="text-xl font-black tracking-tight" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Ticket Overview</h2>
            <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Monitor current support workload across your workspace.</p>

            <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-5">
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Open</p>
                    <p class="text-2xl font-black text-slate-900">{{ $statusCounts['open'] }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">In Progress</p>
                    <p class="text-2xl font-black text-slate-900">{{ $statusCounts['in_progress'] }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Waiting You</p>
                    <p class="text-2xl font-black text-slate-900">{{ $statusCounts['waiting_customer'] }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Resolved</p>
                    <p class="text-2xl font-black text-slate-900">{{ $statusCounts['resolved'] }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Closed</p>
                    <p class="text-2xl font-black text-slate-900">{{ $statusCounts['closed'] }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
            <section class="space-y-6 xl:col-span-4">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Create New Ticket</h2>
                    <p class="mt-1 text-sm text-slate-500">Describe your issue and the support team will respond here.</p>

                    <div class="mt-5 space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Subject</label>
                            <input wire:model="subject" type="text" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);" placeholder="Example: Payment posted but billing did not activate">
                            @error('subject') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Category</label>
                                <select wire:model="category" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                                    <option value="general">General</option>
                                    <option value="billing">Billing</option>
                                    <option value="technical">Technical</option>
                                    <option value="account">Account</option>
                                    <option value="feature-request">Feature Request</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-semibold text-slate-700">Priority</label>
                                <select wire:model="priority" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Details</label>
                            <textarea wire:model="message" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);" placeholder="Share what happened, what you expected, and any error message you saw."></textarea>
                            @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <button wire:click="createTicket" class="w-full rounded-xl px-4 py-2.5 text-sm font-bold text-white transition" style="background-color: var(--tenant-primary);">
                            Submit Ticket
                        </button>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-slate-900">Your Tickets</h2>
                        <select wire:model.live="statusFilter" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600">
                            <option value="all">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting_customer">Waiting You</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div class="space-y-3">
                        @forelse($tickets as $ticket)
                            <button
                                type="button"
                                wire:click="selectTicket({{ $ticket->id }})"
                                class="w-full rounded-2xl border px-4 py-3 text-left transition {{ $activeTicketId === $ticket->id ? 'border-slate-200' : 'border-slate-200 bg-white hover:border-slate-300' }}"
                                style="{{ $activeTicketId === $ticket->id ? 'border-color: color-mix(in srgb, var(--tenant-primary) 25%, white 75%); background-color: color-mix(in srgb, var(--tenant-primary) 10%, white 90%);' : '' }}"
                            >
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $ticket->status === 'closed' ? 'bg-slate-200 text-slate-700' : ($ticket->status === 'resolved' ? '' : 'bg-amber-100 text-amber-700') }}"
                                        style="{{ $ticket->status === 'resolved' ? 'background-color: color-mix(in srgb, var(--tenant-primary) 13%, white 87%); color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);' : '' }}"
                                    >
                                        {{ str_replace('_', ' ', $ticket->status) }}
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ ucfirst($ticket->category) }} • {{ ucfirst($ticket->priority) }} • {{ optional($ticket->last_message_at)->diffForHumans() ?? $ticket->created_at->diffForHumans() }}
                                </p>
                            </button>
                        @empty
                            <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-8 text-center text-sm text-slate-500">No support tickets yet.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="xl:col-span-8">
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    @if($supportUnavailable)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
                            <h3 class="text-lg font-bold text-amber-900">Support Setup Required</h3>
                            <p class="mt-2 text-sm text-amber-800">
                                Support tables are not created yet. Run <code>php artisan migrate</code> in your project, then reload this page.
                            </p>
                        </div>
                    @elseif($activeTicket)
                        <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 md:flex-row md:items-start md:justify-between">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">{{ $activeTicket->subject }}</h2>
                                <p class="mt-1 text-sm text-slate-500">
                                    Ticket #{{ $activeTicket->id }} • {{ ucfirst($activeTicket->category) }} • {{ ucfirst($activeTicket->priority) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold uppercase {{ $activeTicket->status === 'closed' ? 'bg-slate-200 text-slate-700' : ($activeTicket->status === 'resolved' ? '' : 'bg-amber-100 text-amber-700') }}"
                                    style="{{ $activeTicket->status === 'resolved' ? 'background-color: color-mix(in srgb, var(--tenant-primary) 13%, white 87%); color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);' : '' }}"
                                >
                                    {{ str_replace('_', ' ', $activeTicket->status) }}
                                </span>
                                @if(in_array($activeTicket->status, ['open', 'in_progress', 'waiting_customer'], true))
                                    <button wire:click="markResolved({{ $activeTicket->id }})" class="rounded-lg border px-3 py-1.5 text-xs font-semibold" style="border-color: color-mix(in srgb, var(--tenant-primary) 28%, white 72%); background-color: color-mix(in srgb, var(--tenant-primary) 12%, white 88%); color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);">
                                        Mark Resolved
                                    </button>
                                @elseif(in_array($activeTicket->status, ['resolved', 'closed'], true))
                                    <button wire:click="reopenTicket({{ $activeTicket->id }})" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Reopen
                                    </button>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 max-h-[470px] space-y-4 overflow-y-auto pr-1">
                            @foreach($messages as $entry)
                                <div class="flex {{ $entry->author_type === 'tenant' ? 'justify-end' : 'justify-start' }}">
                                    <div
                                        class="max-w-[85%] rounded-2xl px-4 py-3 {{ $entry->author_type === 'tenant' ? 'bg-nsync-green-600 text-white border border-nsync-green-600' : 'bg-slate-100 text-slate-800' }}"
                                    >
                                        <p class="text-xs font-bold uppercase tracking-wide {{ $entry->author_type === 'tenant' ? 'text-white/85' : 'text-slate-500' }}">
                                            {{ $entry->author_type === 'tenant' ? 'You' : 'Support Team' }}
                                        </p>
                                        <p class="mt-1 whitespace-pre-line text-sm leading-6">{{ $entry->message }}</p>
                                        <p class="mt-2 text-[11px] {{ $entry->author_type === 'tenant' ? 'text-white/80' : 'text-slate-500' }}">
                                            {{ $entry->created_at->format('M d, Y g:i A') }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-5 border-t border-slate-100 pt-5">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Reply</label>
                            <textarea wire:model="replyMessage" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);" placeholder="Write your message to support..."></textarea>
                            @error('replyMessage') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            <div class="mt-3 flex justify-end">
                                <button wire:click="sendReply" class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-bold text-white transition hover:bg-slate-700">
                                    Send Reply
                                </button>
                            </div>
                        </div>
                    @else
                        <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                            <h3 class="text-lg font-bold text-slate-900">No ticket selected</h3>
                            <p class="mt-2 text-sm text-slate-500">Create a new ticket to start a support conversation.</p>
                        </div>
                    @endif
                </div>
            </section>
        </div>
    </div>
</div>
