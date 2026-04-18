<?php

use App\Mail\PatchPublishedForTenant;
use App\Models\Patch;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Volt\Component;

new class extends Component {
    public string $title = '';
    public string $description = '';
    public string $sqlStatements = '';

    public function with(): array
    {
        return [
            'patches' => Patch::query()->latest()->get(),
        ];
    }

    public function publishPatch(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'sqlStatements' => ['nullable', 'string'],
        ]);

        $patch = Patch::create([
            'title' => trim($this->title),
            'description' => trim($this->description),
            'sql_migrations' => $this->parsedSqlStatements(),
        ]);

        $recipients = Tenant::query()
            ->active()
            ->whereNotNull('tenant_admin_email')
            ->where('tenant_admin_email', '!=', '')
            ->get(['id', 'name', 'domain', 'tenant_admin', 'tenant_admin_email']);

        $sent = 0;
        $failed = 0;

        foreach ($recipients as $tenant) {
            try {
                Mail::mailer('failover')
                    ->to($tenant->tenant_admin_email)
                    ->send(new PatchPublishedForTenant($patch, $tenant));

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Patch notification email failed for tenant admin.', [
                    'patch_id' => $patch->id,
                    'tenant_id' => $tenant->id,
                    'tenant_admin_email' => $tenant->tenant_admin_email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->reset(['title', 'description', 'sqlStatements']);

        $message = "Patch published. Update notifications sent to {$sent} tenant admin(s)";
        if ($failed > 0) {
            $message .= ", {$failed} failed.";
            $this->dispatch('notify', message: $message, type: 'warning');
            return;
        }

        $this->dispatch('notify', message: $message . '.', type: 'success');
    }

    private function parsedSqlStatements(): array
    {
        return collect(preg_split("/\r\n\s*\r\n|\n\s*\n|\r\s*\r/", $this->sqlStatements) ?: [])
            ->map(fn ($statement) => trim((string) $statement))
            ->filter()
            ->values()
            ->all();
    }
};
?>

<div class="space-y-8">
    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-0">Patch Publisher</h1>
            <p class="text-gray-600 mb-0">Create optional patches for tenant workspaces. Only each tenant admin is notified and can apply them from Update Center.</p>
        </div>

        <div class="space-y-5">
            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Patch Title</label>
                <input wire:model="title" type="text" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500" placeholder="Example: New reporting widgets">
                @error('title') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Description</label>
                <textarea wire:model="description" rows="4" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500" placeholder="Explain what this patch changes and why tenants may want to install it."></textarea>
                @error('description') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-slate-700">Optional SQL Steps</label>
                <textarea wire:model="sqlStatements" rows="8" class="w-full rounded-2xl border border-slate-200 px-4 py-3 font-mono text-sm focus:border-transparent focus:ring-2 focus:ring-nsync-green-500" placeholder="Enter one SQL statement block per paragraph. Leave empty for informational or code-only patches."></textarea>
                <p class="mt-2 text-xs text-slate-500">Separate multiple SQL statements with a blank line so they are applied in order.</p>
                @error('sqlStatements') <p class="mt-2 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end">
                <button wire:click="publishPatch" class="rounded-2xl bg-nsync-green-600 px-6 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-nsync-green-700">
                    Publish Patch
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
        <div class="mb-6">
            <h2 class="text-2xl font-black text-slate-900">Published Patches</h2>
            <p class="mt-2 text-sm text-slate-500">These are the updates that tenant supervisors can review and apply manually.</p>
        </div>

        <div class="space-y-4">
            @forelse($patches as $patch)
                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <h3 class="text-lg font-bold text-slate-900">{{ $patch->title }}</h3>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500">Patch #{{ $patch->id }}</span>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-500">{{ $patch->created_at->format('M d, Y g:i A') }}</span>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-slate-600">{{ $patch->description }}</p>
                    <p class="mt-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                        {{ count($patch->sql_migrations ?? []) }} SQL step{{ count($patch->sql_migrations ?? []) === 1 ? '' : 's' }}
                    </p>
                </div>
            @empty
                <p class="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-slate-500">
                    No patches have been published yet.
                </p>
            @endforelse
        </div>
    </div>
</div>



