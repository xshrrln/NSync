<?php
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\User;
use App\Models\Tenant;
use App\Models\PendingInvite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\TeamInvite;

new class extends Component {
    use WithFileUploads;

    public $search = '';
    public $inviteEmail = '';
    public $selectedRole = 'Team Contributor';
    public $showModal = false;
    public $inviteFile = null;
    public $bulkInviteStatus = '';
    public $bulkInviteStatusType = 'info';
    public bool $bulkInviteSent = false;

    public function with() {
        $tenant = app('currentTenant');
        $canManageMembers = auth()->user()?->hasRole('Team Supervisor') ?? false;
        $canInviteByPlan = $tenant ? $tenant->hasFeature('member-invites') : false;
        $canAssignSupervisorRole = $tenant ? $tenant->hasFeature('role-permissions') : false;
        $canBulkInvite = strtolower((string) ($tenant?->plan ?? 'free')) === 'pro';
        $atMemberLimit = $tenant ? $tenant->hasReachedLimit('members') : false;
        $canInviteMembers = $canManageMembers && $canInviteByPlan && ! $atMemberLimit;
        
        // Fetch members with search filter
        $members = collect();
        if ($tenant) {
            $members = User::where('tenant_id', $tenant->id)
                ->when($this->search, function($q) {
                    $q->where(function($sub) {
                        $sub->where('name', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
                })
                ->latest()
                ->get();
        }

        return [
            'members' => $members,
            'roles' => ['Team Supervisor', 'Team Contributor'],
            'tenant' => $tenant,
            'canManageMembers' => $canManageMembers,
            'canInviteByPlan' => $canInviteByPlan,
            'canAssignSupervisorRole' => $canAssignSupervisorRole,
            'canBulkInvite' => $canBulkInvite,
            'atMemberLimit' => $atMemberLimit,
            'canInviteMembers' => $canInviteMembers,
        ];
    }

    private function ensureSubscriptionAccess(string $title = 'Subscription Required'): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $tenant->requiresSubscriptionRenewal()) {
            return true;
        }

        $this->dispatch('subscription-expired', title: $title, message: $tenant->subscriptionLockMessage());

        return false;
    }

    public function openModal() {
        if (! $this->ensureSubscriptionAccess('Subscription Required to Add Members')) {
            return;
        }

        if (!auth()->user()?->hasRole('Team Supervisor')) {
            return;
        }

        $tenant = app('currentTenant');
        if (! $tenant?->hasFeature('member-invites')) {
            $this->dispatch('notify', message: 'Member invites are not included in your current plan.', type: 'error');
            return;
        }

        if ($tenant->hasReachedLimit('members')) {
            $this->dispatch('notify', message: 'Your workspace has reached its member limit. Upgrade your plan to invite more members.', type: 'error');
            return;
        }

        $this->showModal = true;
        $this->bulkInviteStatus = '';
        $this->bulkInviteStatusType = 'info';
        $this->bulkInviteSent = false;
    }

    public function closeModal() {
        $this->showModal = false;
        $this->reset(['inviteEmail', 'selectedRole', 'inviteFile', 'bulkInviteStatus', 'bulkInviteStatusType', 'bulkInviteSent']);
        $this->resetErrorBag();
    }

    public function updatedInviteFile(): void
    {
        // Prevent single-invite validation messages from showing during bulk upload flow.
        $this->resetValidation('inviteEmail');
        $this->bulkInviteSent = false;
    }

    public function invite() {
        if (! $this->ensureSubscriptionAccess('Subscription Required to Add Members')) {
            return;
        }

        if (!auth()->user()?->hasRole('Team Supervisor')) {
            $this->addError('inviteEmail', 'Only tenant admins can invite members.');
            return;
        }

        $tenant = app('currentTenant');
        if (! $tenant?->hasFeature('member-invites')) {
            $this->addError('inviteEmail', 'Member invites are not available on your current plan.');
            return;
        }

        if ($tenant->hasReachedLimit('members')) {
            $this->addError('inviteEmail', 'Your workspace has reached its member limit. Upgrade your plan to invite more members.');
            return;
        }

        // If a bulk file is attached, treat the main submit button as bulk send.
        if ($this->inviteFile) {
            $this->inviteFromFile();
            return;
        }

        $this->validate([
            'inviteEmail' => 'required|email',
            'selectedRole' => 'required|in:Team Supervisor,Team Contributor',
        ]);

        $tenantId = $tenant?->id;
        if (! $tenant->hasFeature('role-permissions') && $this->selectedRole === 'Team Supervisor') {
            $this->selectedRole = 'Team Contributor';
        }
        
        if (User::where('tenant_id', $tenantId)->where('email', $this->inviteEmail)->exists()) {
            $this->addError('inviteEmail', 'This email is already a team member.');
            return;
        }

        $invite = \App\Models\PendingInvite::create([
            'email' => $this->inviteEmail,
            'role' => $this->selectedRole,
            'tenant_id' => $tenantId,
        ]);

        try {
            $this->sendInviteEmailWithRetry($this->inviteEmail, $invite, Auth::user(), $tenant);
        } catch (\Throwable $e) {
            $this->addError('inviteEmail', 'Invite created, but email delivery failed: ' . Str::limit($e->getMessage(), 140));
            $this->dispatch('notify', message: 'Invite created but email delivery failed. Check your SMTP/network settings.', type: 'error');
            return;
        }

        $this->closeModal();
        session()->flash('message', 'Invite sent successfully!');
    }

    public function inviteFromFile(): void
    {
        // Bulk flow should not inherit single-email validation errors.
        $this->resetValidation('inviteEmail');
        $this->bulkInviteStatus = '';
        $this->bulkInviteStatusType = 'info';
        $this->bulkInviteSent = false;

        if (! $this->ensureSubscriptionAccess('Subscription Required to Add Members')) {
            return;
        }

        if (! auth()->user()?->hasRole('Team Supervisor')) {
            $this->dispatch('notify', message: 'Only tenant admins can bulk invite members.', type: 'error');
            return;
        }

        $tenant = app('currentTenant');
        if (! $tenant?->hasFeature('member-invites')) {
            $this->dispatch('notify', message: 'Member invites are not available on your current plan.', type: 'error');
            return;
        }

        if (strtolower((string) ($tenant->plan ?? 'free')) !== 'pro') {
            $this->dispatch('notify', message: 'Bulk invite upload is available for Pro plan workspaces.', type: 'error');
            return;
        }

        $this->validate([
            'inviteFile' => 'required|file|max:10240|mimes:csv,txt,xlsx',
            'selectedRole' => 'required|in:Team Supervisor,Team Contributor',
        ]);

        $tenantId = (int) ($tenant->id ?? 0);
        if ($tenantId <= 0) {
            $this->dispatch('notify', message: 'No active tenant workspace found.', type: 'error');
            return;
        }

        if (! $tenant->hasFeature('role-permissions') && $this->selectedRole === 'Team Supervisor') {
            $this->selectedRole = 'Team Contributor';
        }

        $emails = $this->extractEmailsFromInviteFile($this->inviteFile);
        if (empty($emails)) {
            $this->addError('inviteFile', 'No valid emails were found in the uploaded file. Use CSV/TXT with one email per line, or XLSX with emails in cells.');
            $this->bulkInviteStatus = 'No valid emails were detected in the uploaded file.';
            $this->bulkInviteStatusType = 'error';
            return;
        }

        $membersLimit = (int) ($tenant->planConfig()['members_limit'] ?? 0);
        $currentMembers = User::where('tenant_id', $tenantId)->count();
        $availableSlots = $membersLimit > 0 ? max(0, $membersLimit - $currentMembers) : PHP_INT_MAX;

        if ($availableSlots <= 0) {
            $this->addError('inviteFile', 'Your workspace has reached its member limit. Upgrade your plan to invite more members.');
            $this->bulkInviteStatus = 'Member limit reached. No invites were sent.';
            $this->bulkInviteStatusType = 'error';
            return;
        }

        $existingMembers = User::where('tenant_id', $tenantId)
            ->whereIn('email', $emails)
            ->pluck('email')
            ->map(fn ($email) => Str::lower((string) $email))
            ->all();

        $existingPendingInvites = PendingInvite::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subDays(7))
            ->whereIn('email', $emails)
            ->get()
            ->keyBy(fn ($invite) => Str::lower((string) $invite->email));

        $skipSet = array_fill_keys($existingMembers, true);
        $inviter = Auth::user();

        // Bulk SMTP calls for 100+ rows can exceed PHP execution limits on slower providers.
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }

        $sent = 0;
        $skipped = 0;
        $failed = 0;
        $firstFailureMessage = null;

        foreach ($emails as $email) {
            if (isset($skipSet[$email])) {
                $skipped++;
                continue;
            }

            if ($sent >= $availableSlots) {
                break;
            }

            try {
                $invite = $existingPendingInvites[$email] ?? null;
                if ($invite) {
                    $invite->role = $this->selectedRole;
                    $invite->token = Str::random(64);
                    $invite->created_at = now();
                    $invite->save();
                } else {
                    $invite = PendingInvite::create([
                        'email' => $email,
                        'role' => $this->selectedRole,
                        'tenant_id' => $tenantId,
                    ]);
                }

                $this->sendInviteEmailWithRetry($email, $invite, $inviter, $tenant);
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $firstFailureMessage ??= Str::limit($e->getMessage(), 160);

                Log::warning('Bulk invite mail send failed.', [
                    'tenant_id' => $tenantId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $processed = $sent + $skipped + $failed;
        $notProcessed = max(0, count($emails) - $processed);

        $this->reset('inviteFile');

        $message = "Bulk invite complete: {$sent} sent";
        if ($skipped > 0) {
            $message .= ", {$skipped} skipped (already member)";
        }
        if ($failed > 0) {
            $message .= ", {$failed} failed (mail delivery error)";
        }
        if ($notProcessed > 0) {
            $message .= ", {$notProcessed} not processed (member limit reached)";
        }
        $message .= '.';
        if ($firstFailureMessage) {
            $message .= ' First error: ' . $firstFailureMessage;
        }

        $this->bulkInviteStatus = $message;
        $this->bulkInviteStatusType = ($sent > 0 && $failed === 0) ? 'success' : 'error';
        $this->bulkInviteSent = ($sent > 0 && $failed === 0);
        session()->flash('message', $message);
        $this->dispatch('notify', message: $message, type: $this->bulkInviteStatusType);
    }

    private function sendInviteEmailWithRetry(string $email, PendingInvite $invite, User $inviter, Tenant $tenant, int $maxAttempts = 3): void
    {
        $mailers = collect([
            (string) config('mail.default', ''),
            'failover',
            'smtp',
        ])
            ->filter()
            ->unique()
            ->values();

        $lastException = null;

        foreach ($mailers as $mailer) {
            $attempt = 0;

            while ($attempt < $maxAttempts) {
                try {
                    Mail::mailer($mailer)->to($email)->send(new TeamInvite($invite, $inviter, $tenant));
                    return;
                } catch (\Throwable $e) {
                    $lastException = $e;
                    $attempt++;

                    if ($attempt < $maxAttempts) {
                        usleep(250000);
                    }
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Invite email delivery failed.');
    }

    private function extractEmailsFromInviteFile($file): array
    {
        $path = $file->getRealPath();
        $extension = Str::lower((string) $file->getClientOriginalExtension());

        $content = '';

        if (in_array($extension, ['csv', 'txt'], true)) {
            $content = (string) @file_get_contents($path);
        } elseif ($extension === 'xlsx') {
            $content = $this->extractTextFromXlsx($path);
        } else {
            $this->addError('inviteFile', 'Unsupported file type. Please upload CSV, TXT, or XLSX.');
            return [];
        }

        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $content, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($email) => Str::lower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    private function extractTextFromXlsx(string $path): string
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->addError('inviteFile', 'XLSX parsing is not available on this server. Please upload CSV.');
            return '';
        }

        $zip = new \ZipArchive();
        $open = $zip->open($path);
        if ($open !== true) {
            $this->addError('inviteFile', 'Unable to read the XLSX file. Please export as CSV and retry.');
            return '';
        }

        $text = '';
        $sharedStrings = [];

        $sharedXmlRaw = (string) $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXmlRaw !== '') {
            $sharedXml = @simplexml_load_string($sharedXmlRaw);
            if ($sharedXml !== false) {
                $sharedXml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

                $sharedStringItems = $sharedXml->xpath('//x:si');
                if (! is_array($sharedStringItems) || empty($sharedStringItems)) {
                    $sharedStringItems = $sharedXml->xpath('//si') ?: [];
                }

                foreach ($sharedStringItems as $si) {
                    $fragments = [];
                    $textNodes = $si->xpath('.//x:t');
                    if (! is_array($textNodes) || empty($textNodes)) {
                        $textNodes = $si->xpath('.//t') ?: [];
                    }

                    foreach ($textNodes as $node) {
                        $fragments[] = (string) $node;
                    }
                    $sharedStrings[] = trim(implode('', $fragments));
                }
            }
        }

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (! str_starts_with($name, 'xl/worksheets/sheet')) {
                continue;
            }

            $sheetRaw = (string) $zip->getFromIndex($i);
            if ($sheetRaw === '') {
                continue;
            }

            $sheetXml = @simplexml_load_string($sheetRaw);
            if ($sheetXml === false) {
                continue;
            }

            $sheetXml->registerXPathNamespace('x', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $cells = $sheetXml->xpath('//x:c');
            if (! is_array($cells) || empty($cells)) {
                $cells = $sheetXml->xpath('//c') ?: [];
            }

            foreach ($cells as $cell) {
                $cellType = (string) ($cell['t'] ?? '');
                $value = '';

                if ($cellType === 's') {
                    $sharedValueNodes = $cell->xpath('./x:v');
                    if (! is_array($sharedValueNodes) || empty($sharedValueNodes)) {
                        $sharedValueNodes = $cell->xpath('./v') ?: [];
                    }
                    $sharedIndex = isset($sharedValueNodes[0]) ? (int) $sharedValueNodes[0] : -1;
                    $value = (string) ($sharedStrings[$sharedIndex] ?? '');
                } elseif ($cellType === 'inlineStr') {
                    $fragments = [];
                    $textNodes = $cell->xpath('.//x:t');
                    if (! is_array($textNodes) || empty($textNodes)) {
                        $textNodes = $cell->xpath('.//t') ?: [];
                    }

                    foreach ($textNodes as $node) {
                        $fragments[] = (string) $node;
                    }
                    $value = implode('', $fragments);
                } else {
                    $valueNodes = $cell->xpath('./x:v');
                    if (! is_array($valueNodes) || empty($valueNodes)) {
                        $valueNodes = $cell->xpath('./v') ?: [];
                    }
                    $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';
                }

                if ($value !== '') {
                    $text .= ' ' . $value;
                }
            }
        }

        $zip->close();

        return $text;
    }

    public function remove($userId) {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!auth()->user()?->hasRole('Team Supervisor')) {
            return;
        }

        $user = User::where('id', $userId)->where('tenant_id', app('currentTenant')?->id)->first();
        if ($user && $user->id !== Auth::id()) {
            $user->delete();
        }
    }
}; ?>

<div class="bg-white min-h-screen">
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-col gap-4 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-0">Team Members</h1>
                    <p class="text-gray-600 mb-0">{{ $tenant?->users?->count() ?? 0 }} total members</p>
                </div>
                
                <div class="flex items-center gap-3">
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </span>
                        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search members..." 
                               class="pl-10 pr-4 py-2 bg-gray-50 border-none rounded-lg text-sm focus:ring-2 focus:ring-nsync-green-500 w-64 transition">
                    </div>

                    @if($canInviteMembers)
                    <button wire:click="openModal" type="button" class="px-6 py-3 bg-nsync-green-600 text-white font-bold text-sm rounded-lg hover:bg-nsync-green-700 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="3">
                            <path d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                        </svg>
                        Add Member
                    </button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8">
        @if($canManageMembers && ! $canInviteByPlan)
            <div class="mb-4 p-4 bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-xl">
                Your current plan does not include member invites.
            </div>
        @elseif($canManageMembers && $atMemberLimit)
            <div class="mb-4 p-4 bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-xl">
                You have reached your member limit for this plan.
            </div>
        @endif

        @if(session()->has('message'))
            <div class="mb-4 p-4 bg-emerald-50 border border-emerald-100 text-emerald-700 text-sm rounded-xl">
                {{ session('message') }}
            </div>
        @endif

        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50/50">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-bold text-gray-500 uppercase tracking-wide">Member</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-gray-500 uppercase tracking-wide">Role</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-gray-500 uppercase tracking-wide">Joined</th>
                            <th class="px-6 py-4 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($members as $user)
                            <tr class="hover:bg-gray-50/50 transition-colors" wire:key="user-{{ $user->id }}">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-nsync-green-600 rounded-full flex items-center justify-center text-white font-bold text-xs">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-bold text-gray-900">{{ $user->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full {{ $user->hasRole('Team Supervisor') ? 'bg-purple-50 text-purple-700 border border-purple-100' : 'bg-emerald-50 text-emerald-700 border border-emerald-100' }}">
                                        {{ $user->getRoleNames()->first() ?? 'Contributor' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-gray-500">
                                    {{ $user->created_at->format('M d, Y') }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if($canManageMembers && $user->id !== auth()->id())
                                        <button wire:click="remove({{ $user->id }})" wire:confirm="Are you sure you want to remove this member?" class="text-sm font-semibold text-red-500 hover:text-red-700 transition">Remove</button>
                                    @elseif($user->id === auth()->id())
                                        <span class="text-sm font-semibold text-gray-400">You</span>
                                    @else
                                        <span class="text-sm font-semibold text-gray-400">Member</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-20 text-center">
                                    <p class="text-gray-400 text-sm">No members found matching your search.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($showModal)
        <div class="fixed inset-0 z-[9999] bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full overflow-hidden border border-gray-100" @click.away="$wire.closeModal()">
                <div class="p-6 border-b flex items-center justify-between bg-gray-50/50">
                    <h3 class="text-xl font-bold text-gray-900">Invite Member</h3>
                    <button wire:click="closeModal" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <form wire:submit.prevent="invite" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">Email Address</label>
                        <input wire:model="inviteEmail" type="email" placeholder="colleague@company.com" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-nsync-green-500 transition text-sm">
                        @if(! $inviteFile)
                            @error('inviteEmail') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        @endif
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-2">Role Permissions</label>
                        @if($canAssignSupervisorRole)
                            <select wire:model="selectedRole" class="w-full px-4 py-3 bg-gray-50 border-none rounded-xl focus:ring-2 focus:ring-nsync-green-500 transition text-sm">
                                @foreach($roles as $role)
                                    <option value="{{ $role }}">{{ $role }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" value="Team Contributor" readonly class="w-full px-4 py-3 bg-gray-100 border-none rounded-xl text-sm text-gray-600">
                            <p class="mt-1 text-xs text-gray-500">Role permissions are not available on your current plan.</p>
                        @endif
                    </div>

                    @if($canBulkInvite)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Bulk Invite File (Pro)</label>
                            <input wire:model="inviteFile" type="file" accept=".csv,.txt,.xlsx" class="w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-nsync-green-600 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-nsync-green-700">
                            <p class="mt-2 text-xs text-gray-500">Upload CSV/TXT/XLSX with one email per cell or line. We will invite all valid emails at once.</p>
                            @error('inviteFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            <button
                                type="button"
                                wire:click.stop.prevent="inviteFromFile"
                                wire:loading.attr="disabled"
                                @disabled($bulkInviteSent)
                                class="mt-3 w-full rounded-xl px-4 py-2.5 text-sm font-semibold transition {{ $bulkInviteSent ? 'cursor-not-allowed bg-gray-300 text-gray-600' : 'bg-emerald-700 text-white hover:bg-emerald-800' }}"
                            >
                                <span wire:loading.remove wire:target="inviteFromFile">
                                    {{ $bulkInviteSent ? 'Sent' : 'Send Bulk Invites' }}
                                </span>
                                <span wire:loading wire:target="inviteFromFile">Processing File...</span>
                            </button>

                            @if($bulkInviteStatus)
                                <div class="mt-3 rounded-lg px-3 py-2 text-xs {{ $bulkInviteStatusType === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' : 'bg-rose-50 text-rose-700 border border-rose-100' }}">
                                    {{ $bulkInviteStatus }}
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
                            Bulk invite upload is available for Pro plan workspaces.
                        </div>
                    @endif

                    <div class="flex gap-3 pt-4">
                        <button type="button" wire:click="closeModal" class="flex-1 px-4 py-3 text-gray-600 bg-gray-100 font-semibold text-sm rounded-xl hover:bg-gray-200 transition">Cancel</button>
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            @disabled($inviteFile && $bulkInviteSent)
                            class="flex-1 px-4 py-3 font-semibold text-sm rounded-xl transition {{ ($inviteFile && $bulkInviteSent) ? 'cursor-not-allowed bg-gray-300 text-gray-600' : 'bg-nsync-green-600 text-white hover:bg-nsync-green-700' }}"
                        >
                            <span wire:loading.remove wire:target="invite,inviteFromFile">
                                @if($inviteFile)
                                    {{ $bulkInviteSent ? 'Sent' : 'Send Invite' }}
                                @else
                                    Send Invite
                                @endif
                            </span>
                            <span wire:loading wire:target="invite,inviteFromFile">
                                {{ $inviteFile ? 'Sending Invites...' : 'Sending...' }}
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
