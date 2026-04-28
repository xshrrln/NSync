<?php

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\Volt\Component;

new class extends Component {
    use WithFileUploads;

    public string $search = '';
    public string $statusFilter = 'all';
    public string $priorityFilter = 'all';
    public ?int $activeTicketId = null;
    public string $replyMessage = '';
    public array $replyImages = [];

    public function with(): array
    {
        $this->markSupportNotificationsRead();

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

        $query = SupportTicket::query()
            ->with('tenant')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->priorityFilter !== 'all') {
            $query->where('priority', $this->priorityFilter);
        }

        if (filled($this->search)) {
            $search = '%' . trim($this->search) . '%';
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('subject', 'like', $search)
                    ->orWhere('requester_name', 'like', $search)
                    ->orWhere('requester_email', 'like', $search)
                    ->orWhereHas('tenant', fn ($tenantQuery) => $tenantQuery->where('name', 'like', $search));
            });
        }

        $tickets = $query->get();
        $activeTicket = null;

        if ($this->activeTicketId) {
            $activeTicket = $tickets->firstWhere('id', $this->activeTicketId)
                ?? SupportTicket::query()->with('tenant')->find($this->activeTicketId);
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
                'open' => SupportTicket::where('status', 'open')->count(),
                'in_progress' => SupportTicket::where('status', 'in_progress')->count(),
                'waiting_customer' => SupportTicket::where('status', 'waiting_customer')->count(),
                'resolved' => SupportTicket::where('status', 'resolved')->count(),
                'closed' => SupportTicket::where('status', 'closed')->count(),
            ],
        ];
    }

    public function selectTicket(int $ticketId): void
    {
        $this->activeTicketId = $ticketId;
        $this->reset(['replyMessage', 'replyImages']);
    }

    public function removeReplyImage(int $index): void
    {
        if (! isset($this->replyImages[$index])) {
            return;
        }

        unset($this->replyImages[$index]);
        $this->replyImages = array_values($this->replyImages);
    }

    public function updateTicketStatus(string $status, ?int $ticketId = null): void
    {
        if (! $this->supportTablesReady()) {
            return;
        }

        $allowedStatuses = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];
        if (! in_array($status, $allowedStatuses, true)) {
            return;
        }

        $targetId = $ticketId ?? $this->activeTicketId;
        if (! $targetId) {
            return;
        }

        $ticket = SupportTicket::find($targetId);
        if (! $ticket) {
            return;
        }

        $payload = [
            'status' => $status,
        ];

        if ($status === 'resolved') {
            $payload['resolved_at'] = now();
            $payload['closed_at'] = null;
        } elseif ($status === 'closed') {
            $payload['closed_at'] = now();
            $payload['resolved_at'] = $ticket->resolved_at ?? now();
        } else {
            $payload['resolved_at'] = null;
            $payload['closed_at'] = null;
        }

        $ticket->update($payload);
        $this->dispatch('notify', message: 'Ticket status updated.', type: 'success');
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

        $ticket = SupportTicket::find($this->activeTicketId);
        if (! $ticket) {
            $this->dispatch('notify', message: 'Selected support ticket was not found.', type: 'error');
            return;
        }

        $replyMessage = trim($this->replyMessage);

        if ($replyMessage === '' && count($this->replyImages) === 0) {
            $this->addError('replyMessage', 'Add a reply or upload at least one screenshot.');
            return;
        }

        $user = Auth::user();
        $storedAttachments = $this->storeAttachments($this->replyImages, $ticket->tenant_id);
        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'author_type' => 'admin',
            'author_name' => $user?->name ?? 'Platform Support',
            'author_email' => $user?->email,
            'message' => $replyMessage !== '' ? $replyMessage : 'Screenshot attached for support review.',
            'attachments' => [],
            'attachments' => $storedAttachments,
        ]);

        $ticket->update([
            'status' => 'waiting_customer',
            'last_message_at' => now(),
            'closed_at' => null,
        ]);

        $this->reset(['replyMessage', 'replyImages']);
        $this->dispatch('notify', message: 'Reply sent to tenant.', type: 'success');
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
        if (Schema::hasTable('support_tickets') && Schema::hasTable('support_ticket_messages')) {
            return true;
        }

        try {
            if (! Schema::hasTable('support_tickets')) {
                Schema::create('support_tickets', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('tenant_id')->nullable();
                    $table->unsignedBigInteger('requester_user_id')->nullable();
                    $table->string('requester_name')->nullable();
                    $table->string('requester_email')->nullable();
                    $table->string('subject');
                    $table->string('category', 50)->default('general');
                    $table->string('priority', 20)->default('normal');
                    $table->string('status', 30)->default('open');
                    $table->timestamp('last_message_at')->nullable();
                    $table->timestamp('resolved_at')->nullable();
                    $table->timestamp('closed_at')->nullable();
                    $table->timestamps();

                    $table->index(['tenant_id', 'status']);
                    $table->index(['tenant_id', 'priority']);
                    $table->index('last_message_at');
                });
            }

            if (! Schema::hasTable('support_ticket_messages')) {
                Schema::create('support_ticket_messages', function (Blueprint $table): void {
                    $table->id();
                    $table->unsignedBigInteger('support_ticket_id');
                    $table->unsignedBigInteger('tenant_id')->nullable();
                    $table->string('author_type', 30);
                    $table->string('author_name');
                    $table->string('author_email')->nullable();
                    $table->text('message');
                    $table->json('attachments')->nullable();
                    $table->timestamps();

                    $table->index(['support_ticket_id', 'created_at']);
                    $table->index(['tenant_id', 'created_at']);
                });
            }

            if (Schema::hasTable('support_ticket_messages') && ! Schema::hasColumn('support_ticket_messages', 'attachments')) {
                Schema::table('support_ticket_messages', function (Blueprint $table): void {
                    $table->json('attachments')->nullable()->after('message');
                });
            }
        } catch (\Throwable $e) {
            Log::warning('Support table bootstrap failed.', ['error' => $e->getMessage()]);
            return false;
        }

        return Schema::hasTable('support_tickets')
            && Schema::hasTable('support_ticket_messages')
            && Schema::hasColumn('support_ticket_messages', 'attachments');
    }

    private function markSupportNotificationsRead(): void
    {
        $user = Auth::user();

        if (! $user || ! Schema::hasTable('notifications')) {
            return;
        }

        $user->unreadNotifications()
            ->whereIn('type', [
                \App\Notifications\AdminSupportTicketNotification::class,
                \App\Notifications\AdminTenantMessageNotification::class,
            ])
            ->update(['read_at' => now()]);
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

<div class="space-y-6" x-data="{
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
    @if($supportUnavailable)
        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5">
            <h3 class="text-lg font-bold text-amber-900">Support Temporarily Unavailable</h3>
            <p class="mt-2 text-sm text-amber-800">
                We could not initialize support storage from this environment. Please verify database create-table permissions and reload.
            </p>
        </div>
    @endif

    <div class="rounded-3xl border border-green-200 bg-white px-8 py-8 shadow-sm">
        <h1 class="text-3xl font-black tracking-tight text-green-700">Support Desk</h1>
        <p class="mt-2 text-sm text-slate-600">Manage all tenant support conversations from one central workspace.</p>
        <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-5">
            <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-green-700">Open</p>
                <p class="text-2xl font-black text-slate-900">{{ $statusCounts['open'] }}</p>
            </div>
            <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-green-700">In Progress</p>
                <p class="text-2xl font-black text-slate-900">{{ $statusCounts['in_progress'] }}</p>
            </div>
            <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-green-700">Waiting Customer</p>
                <p class="text-2xl font-black text-slate-900">{{ $statusCounts['waiting_customer'] }}</p>
            </div>
            <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-green-700">Resolved</p>
                <p class="text-2xl font-black text-slate-900">{{ $statusCounts['resolved'] }}</p>
            </div>
            <div class="rounded-2xl border border-green-100 bg-green-50 px-4 py-3">
                <p class="text-[11px] uppercase tracking-wide text-green-700">Closed</p>
                <p class="text-2xl font-black text-slate-900">{{ $statusCounts['closed'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <section class="xl:col-span-4">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="space-y-3">
                    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search subject, tenant, requester..." class="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500">
                    <div class="grid grid-cols-2 gap-3">
                        <select wire:model.live="statusFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-600">
                            <option value="all">All Statuses</option>
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="waiting_customer">Waiting Customer</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                        <select wire:model.live="priorityFilter" class="rounded-xl border border-slate-200 px-3 py-2.5 text-xs font-semibold text-slate-600">
                            <option value="all">All Priorities</option>
                            <option value="urgent">Urgent</option>
                            <option value="high">High</option>
                            <option value="normal">Normal</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                <div class="mt-5 space-y-3">
                    @forelse($tickets as $ticket)
                        <button
                            type="button"
                            wire:click="selectTicket({{ $ticket->id }})"
                            class="w-full rounded-2xl border px-4 py-3 text-left transition {{ $activeTicketId === $ticket->id ? 'border-nsync-green-200 bg-nsync-green-50' : 'border-slate-200 bg-white hover:border-slate-300' }}"
                        >
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $ticket->subject }}</p>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase {{ $ticket->priority === 'urgent' ? 'bg-rose-100 text-rose-700' : ($ticket->priority === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ $ticket->priority }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $ticket->tenant?->name ?? 'Unknown Tenant' }} • {{ $ticket->requester_name ?? 'Unknown Requester' }}
                            </p>
                            <p class="mt-1 text-[11px] text-slate-400">
                                {{ str_replace('_', ' ', $ticket->status) }} • {{ optional($ticket->last_message_at)->diffForHumans() ?? $ticket->created_at->diffForHumans() }}
                            </p>
                        </button>
                    @empty
                        <p class="rounded-2xl border border-dashed border-slate-300 px-4 py-10 text-center text-sm text-slate-500">No support tickets matched your filters.</p>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="xl:col-span-8">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm {{ $activeTicket ? 'h-[28rem] overflow-hidden' : '' }}">
                @if($supportUnavailable)
                    <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                        <h3 class="text-lg font-bold text-slate-900">Support is not initialized</h3>
                        <p class="mt-2 text-sm text-slate-500">Run migrations to enable the support desk.</p>
                    </div>
                @elseif($activeTicket)
                    <div class="flex h-full min-h-0 flex-col overflow-hidden">
                        <div class="shrink-0 border-b border-slate-100 px-5 pb-4 pt-2">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">{{ $activeTicket->subject }}</h2>
                                    <p class="mt-1 text-sm text-slate-500">
                                        Ticket #{{ $activeTicket->id }} • Tenant: {{ $activeTicket->tenant?->name ?? 'N/A' }} • Requester: {{ $activeTicket->requester_name ?? 'Unknown' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button wire:click="updateTicketStatus('in_progress', {{ $activeTicket->id }})" class="rounded-lg border border-nsync-green-200 bg-nsync-green-50 px-3 py-1.5 text-xs font-semibold text-nsync-green-700 hover:bg-nsync-green-100">In Progress</button>
                                    <button wire:click="updateTicketStatus('waiting_customer', {{ $activeTicket->id }})" class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100">Waiting Customer</button>
                                    <button wire:click="updateTicketStatus('resolved', {{ $activeTicket->id }})" class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">Resolved</button>
                                    <button wire:click="updateTicketStatus('closed', {{ $activeTicket->id }})" class="rounded-lg border border-slate-300 bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200">Closed</button>
                                </div>
                            </div>
                        </div>

                        <div class="support-ticket-messages flex-1 min-h-0 max-h-full overflow-y-scroll space-y-4 px-5 py-4" style="overscroll-behavior: contain; scrollbar-gutter: stable;">
                            @foreach($messages as $entry)
                                @php($isSupportReply = in_array($entry->author_type, ['admin', 'ai'], true))
                                <div class="flex {{ $isSupportReply ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $isSupportReply ? 'bg-nsync-green-600 text-white border border-nsync-green-600' : 'bg-slate-100 text-slate-800' }}">
                                        <p class="text-xs font-bold uppercase tracking-wide {{ $isSupportReply ? 'text-white/85' : 'text-slate-500' }}">
                                            {{ $isSupportReply ? ($entry->author_name ?: 'Support') : ($entry->author_name ?: 'Tenant User') }}
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
                                        <p class="mt-2 text-[11px] {{ $isSupportReply ? 'text-white/80' : 'text-slate-500' }}">
                                            {{ $this->displayTimestamp($entry->created_at) }}
                                        </p>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="shrink-0 border-t border-slate-100 px-6 pb-6 pt-5" x-ref="ticketComposer">
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
                    </div>
                @else
                    <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                        <h3 class="text-lg font-bold text-slate-900">No ticket selected</h3>
                        <p class="mt-2 text-sm text-slate-500">Choose a ticket from the left panel to start handling support.</p>
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
