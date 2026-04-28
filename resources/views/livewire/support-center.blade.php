<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Support\AiSupportResponder;
use App\Support\SupportAdminNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public string $subject = '';
    public string $category = 'general';
    public string $priority = 'normal';
    public string $message = '';
    public string $replyMessage = '';
    public ?int $activeTicketId = null;
    public string $statusFilter = 'all';
    public array $ticketImages = [];
    public array $replyImages = [];

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
            'message' => ['nullable', 'string', 'max:5000'],
            'ticketImages' => ['nullable', 'array', 'max:4'],
            'ticketImages.*' => ['image', 'max:5120'],
        ]);

        $tenant = app('currentTenant');
        $user = Auth::user();

        $ticket = null;
        $createdMessage = null;
        $tenantMessage = trim($this->message);

        if ($tenantMessage === '' && count($this->ticketImages) === 0) {
            $this->addError('message', 'Add a message or upload at least one screenshot.');
            return;
        }

        $storedMessage = $tenantMessage !== '' ? $tenantMessage : 'Screenshot attached for support review.';
        $storedAttachments = $this->storeAttachments($this->ticketImages, $tenant?->id);

        DB::transaction(function () use ($tenant, $user, &$ticket, &$createdMessage, $storedMessage, $storedAttachments): void {
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

            $createdMessage = SupportTicketMessage::create([
                'support_ticket_id' => $ticket->id,
                'tenant_id' => $tenant->id,
                'author_type' => 'tenant',
                'author_name' => $user?->name ?? 'Tenant User',
                'author_email' => $user?->email,
                'message' => $storedMessage,
                'attachments' => $storedAttachments,
            ]);

            $this->activeTicketId = $ticket->id;
        });

        if ($ticket) {
            app(SupportAdminNotifier::class)->notifyTenantMessage($ticket, $createdMessage, 'ticket_created');
            $this->createAiReply($ticket, $tenantMessage !== '' ? $tenantMessage : 'User attached screenshot(s) without additional text.');
        }

        $this->reset(['subject', 'message', 'ticketImages']);
        $this->category = 'general';
        $this->priority = 'normal';
        $this->dispatch('notify', message: 'Support ticket submitted successfully.', type: 'success');
    }

    public function selectTicket(int $ticketId): void
    {
        $this->activeTicketId = $ticketId;
        $this->reset('replyMessage');
    }

    public function removeTicketImage(int $index): void
    {
        if (! isset($this->ticketImages[$index])) {
            return;
        }

        unset($this->ticketImages[$index]);
        $this->ticketImages = array_values($this->ticketImages);
    }

    public function removeReplyImage(int $index): void
    {
        if (! isset($this->replyImages[$index])) {
            return;
        }

        unset($this->replyImages[$index]);
        $this->replyImages = array_values($this->replyImages);
    }

    public function sendReply(): void
    {
        if (! $this->supportTablesReady()) {
            $this->dispatch('notify', message: 'Support is not initialized yet. Please run database migrations first.', type: 'error');
            return;
        }

        $this->validate([
            'replyMessage' => ['nullable', 'string', 'max:5000'],
            'replyImages' => ['nullable', 'array', 'max:4'],
            'replyImages.*' => ['image', 'max:5120'],
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

        $tenantMessage = trim($this->replyMessage);

        if ($tenantMessage === '' && count($this->replyImages) === 0) {
            $this->addError('replyMessage', 'Add a reply or upload at least one screenshot.');
            return;
        }

        $storedMessage = $tenantMessage !== '' ? $tenantMessage : 'Screenshot attached for support review.';
        $storedAttachments = $this->storeAttachments($this->replyImages, $tenant?->id);

        $createdMessage = SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'tenant_id' => $tenant->id,
            'author_type' => 'tenant',
            'author_name' => $user?->name ?? 'Tenant User',
            'author_email' => $user?->email,
            'message' => $storedMessage,
            'attachments' => $storedAttachments,
        ]);

        $ticket->update([
            'status' => 'open',
            'last_message_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
        ]);

        app(SupportAdminNotifier::class)->notifyTenantMessage($ticket->fresh('tenant'), $createdMessage, 'message_sent');
        $this->createAiReply($ticket->fresh(), $tenantMessage !== '' ? $tenantMessage : 'User attached screenshot(s) without additional text.');

        $this->reset(['replyMessage', 'replyImages']);
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

    public function displayTimestamp($value): string
    {
        if (! $value) {
            return '-';
        }

        return $value->copy()->timezone(config('app.timezone'))->format('M d, Y g:i A');
    }

    private function supportTablesReady(): bool
    {
        if (! Schema::hasTable('support_tickets') || ! Schema::hasTable('support_ticket_messages')) {
            return false;
        }

        try {
            if (! Schema::hasColumn('support_ticket_messages', 'attachments')) {
                Schema::table('support_ticket_messages', function ($table): void {
                    $table->json('attachments')->nullable()->after('message');
                });
            }
        } catch (\Throwable) {
            return false;
        }

        return Schema::hasColumn('support_ticket_messages', 'attachments');
    }

    private function createAiReply(?SupportTicket $ticket, string $tenantMessage): void
    {
        if (! $ticket) {
            return;
        }

        $responder = app(AiSupportResponder::class);
        $reply = $responder->replyFor($ticket, $tenantMessage);

        if (! $reply) {
            return;
        }

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'author_type' => 'ai',
            'author_name' => $responder->assistantName(),
            'author_email' => null,
            'message' => $reply,
            'attachments' => [],
        ]);

        $ticket->update([
            'status' => 'waiting_customer',
            'last_message_at' => now(),
            'closed_at' => null,
        ]);
    }

    private function storeAttachments(array $files, ?int $tenantId): array
    {
        return collect($files)
            ->filter()
            ->map(function ($file) use ($tenantId) {
                $path = $file->store('support/' . ($tenantId ?: 'shared'), 'public');

                return [
                    'path' => $path,
                    'url' => Storage::disk('public')->url($path),
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ];
            })
            ->values()
            ->all();
    }
};
?>

<div class="min-h-full bg-white" x-data="{
    handlePastedImages(event, property) {
        const files = Array.from(event.clipboardData?.files ?? []).filter((file) => file.type.startsWith('image/'));

        if (!files.length) {
            return;
        }

        event.preventDefault();

        $wire.uploadMultiple(
            property,
            files,
            () => {},
            () => {
                if (window.NSyncSystemToast?.open) {
                    window.NSyncSystemToast.open({ type: 'error', message: 'Screenshot paste failed. Please try again.' });
                }
            },
            () => {}
        );
    }
}">
    <style>
        .support-ticket-messages {
            scrollbar-width: thin;
            scrollbar-color: rgba(148,163,184,0.8) rgba(226,232,240,0.8);
        }

        .support-ticket-messages::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .support-ticket-messages::-webkit-scrollbar-track {
            background: rgba(226,232,240,0.8);
        }

        .support-ticket-messages::-webkit-scrollbar-thumb {
            background-color: rgba(148,163,184,0.8);
            border-radius: 9999px;
            border: 2px solid rgba(226,232,240,0.8);
        }
    </style>

}">
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
                            <textarea wire:model="message" @paste="handlePastedImages($event, 'ticketImages')" rows="5" class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);" placeholder="Share what happened, what you expected, and any error message you saw."></textarea>
                            @error('message') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-semibold text-slate-700">Screenshots</label>
                            <input type="file" wire:model="ticketImages" accept="image/*" multiple class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm">
                            <p class="mt-1 text-xs text-slate-500">Upload or paste up to 4 images. Maximum 5MB each.</p>
                            @error('ticketImages') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @error('ticketImages.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            @if(!empty($ticketImages))
                                <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                                    @foreach($ticketImages as $index => $image)
                                        <div class="relative">
                                            <button type="button" wire:click="removeTicketImage({{ $index }})" class="absolute right-2 top-2 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900/80 text-sm font-bold text-white hover:bg-slate-900" aria-label="Remove image">
                                                ×
                                            </button>
                                            <img src="{{ $image->temporaryUrl() }}" alt="Ticket image preview" class="h-24 w-full rounded-xl border border-slate-200 object-cover">
                                        </div>
                                    @endforeach
                                </div>
                            @endif
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

            <section class="min-h-0 xl:col-span-8 {{ $activeTicket ? 'h-[26rem]' : '' }}">
                <div class="{{ $activeTicket ? 'xl:sticky xl:top-6 h-full overflow-hidden' : '' }}">
                <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm {{ $activeTicket ? 'h-full' : '' }}">
                    @if($supportUnavailable)
                        <div class="m-6 rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
                            <h3 class="text-lg font-bold text-amber-900">Support Setup Required</h3>
                            <p class="mt-2 text-sm text-amber-800">
                                Support tables are not created yet. Run <code>php artisan migrate</code> in your project, then reload this page.
                            </p>
                        </div>
                    @elseif($activeTicket)
                        <div class="flex h-full min-h-0 flex-col overflow-hidden" x-ref="ticketPanel">
                            <div class="shrink-0 flex flex-col gap-3 border-b border-slate-100 px-5 py-4 md:flex-row md:items-start md:justify-between" x-ref="ticketHeader">
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

                            <div class="support-ticket-messages flex-1 min-h-0 h-full overflow-y-scroll space-y-3 px-5 py-3" style="overscroll-behavior: contain; scrollbar-gutter: stable;">
                                @foreach($messages as $entry)
                                    <div class="mb-4 flex {{ $entry->author_type === 'tenant' ? 'justify-end' : 'justify-start' }}">
                                        <div
                                            class="max-w-[85%] rounded-2xl px-4 py-3 {{ $entry->author_type === 'tenant' ? 'bg-nsync-green-600 text-white border border-nsync-green-600' : 'bg-slate-100 text-slate-800' }}"
                                        >
                                            <p class="text-xs font-bold uppercase tracking-wide {{ $entry->author_type === 'tenant' ? 'text-white/85' : 'text-slate-500' }}">
                                                {{ $entry->author_type === 'tenant' ? 'You' : ($entry->author_type === 'ai' ? ($entry->author_name ?: 'NSync Assistant') : 'Support Team') }}
                                            </p>
                                            <p class="mt-1 whitespace-pre-line text-sm leading-6">{{ $entry->message }}</p>
                                            @if(!empty($entry->attachments))
                                                <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-3">
                                                    @foreach($entry->attachments as $attachment)
                                                        <a href="{{ data_get($attachment, 'url') }}" target="_blank" rel="noopener">
                                                            <img src="{{ data_get($attachment, 'url') }}" alt="{{ data_get($attachment, 'name', 'Support attachment') }}" class="h-28 w-full rounded-xl border border-slate-200 object-cover">
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <p class="mt-2 text-[11px] {{ $entry->author_type === 'tenant' ? 'text-white/80' : 'text-slate-500' }}">
                                                {{ $this->displayTimestamp($entry->created_at) }}
                                            </p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="shrink-0 border-t border-slate-100 px-5 pb-5 pt-4" x-ref="ticketComposer">
                                <div class="overflow-hidden rounded-[28px] bg-[#242526] shadow-sm focus-within:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                                @if(!empty($replyImages))
                                    <div class="grid grid-cols-2 gap-2 border-b border-white/10 bg-[#1f2021] p-3 md:grid-cols-4">
                                        @foreach($replyImages as $index => $image)
                                            <div class="relative">
                                                <button type="button" wire:click="removeReplyImage({{ $index }})" class="absolute right-2 top-2 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-slate-900/80 text-sm font-bold text-white hover:bg-slate-900" aria-label="Remove image">
                                                    ×
                                                </button>
                                                <img src="{{ $image->temporaryUrl() }}" alt="Reply image preview" class="h-24 w-full rounded-2xl border border-white/10 object-cover">
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <div class="flex items-center gap-1.5 px-2 py-2">
                                    <label class="inline-flex h-10 w-10 cursor-pointer items-center justify-center rounded-full transition hover:bg-white/10" style="color: var(--tenant-primary);" aria-label="Attach image">
                                        <input type="file" wire:model="replyImages" accept="image/*" multiple class="hidden">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M12 5a1 1 0 0 1 1 1v5h5a1 1 0 1 1 0 2h-5v5a1 1 0 1 1-2 0v-5H6a1 1 0 1 1 0-2h5V6a1 1 0 0 1 1-1Z"/>
                                        </svg>
                                    </label>

                                    <div class="flex h-11 flex-1 items-center rounded-full bg-[#3a3b3c] px-4">
                                        <textarea wire:model="replyMessage" @keydown.enter.prevent="if (!$event.shiftKey) { $wire.sendReply() }" @keydown.shift.enter.stop @paste="handlePastedImages($event, 'replyImages')" rows="1" class="max-h-24 min-h-[24px] flex-1 resize-none overflow-y-auto border-0 bg-transparent px-0 py-1 text-[15px] leading-6 text-black placeholder:text-slate-300 focus:border-transparent focus:ring-0" style="color: black; resize: none;" placeholder="Write your message to support..."></textarea>

                                    <button wire:click="sendReply" class="inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/10" style="color: var(--tenant-primary);" aria-label="Send reply">
                                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path d="M3.293 11.293 18.586 2.879c1.247-.686 2.689.442 2.347 1.836l-3.02 12.302a1.75 1.75 0 0 1-2.69 1.05l-3.932-2.621-2.41 2.41a1 1 0 0 1-1.707-.707v-4.05L3.7 12.96a.95.95 0 0 1-.407-1.667Zm5.881 1.95v1.492l1.169-1.168 6.137-6.137-7.306 5.813Z"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="shrink-0 px-6 pb-2" x-ref="ticketComposerErrors">
                                @error('replyMessage') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('replyImages') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                                @error('replyImages.*') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @else
                        <div class="flex min-h-[24rem] items-center justify-center rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                            <h3 class="text-lg font-bold text-slate-900">No ticket selected</h3>
                            <p class="mt-2 text-sm text-slate-500">Create a new ticket to start a support conversation.</p>
                        </div>
                    @endif
                </div>
                </div>
            </section>
        </div>
    </div>
</div>
