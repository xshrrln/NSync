<?php

use App\Models\Patch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $tenant = app('currentTenant');
        $appliedPatchIds = collect($tenant?->patches_applied ?? [])->map(fn ($id) => (int) $id)->all();
        $patches = Patch::query()->latest()->get();
        $canApplyUpdates = $this->isTenantAdmin($tenant);

        return [
            'tenant' => $tenant,
            'patches' => $patches,
            'appliedPatchIds' => $appliedPatchIds,
            'canApplyUpdates' => $canApplyUpdates,
            'pendingCount' => $patches->whereNotIn('id', $appliedPatchIds)->count(),
        ];
    }

    public function applyPatch(int $patchId): void
    {
        $tenant = app('currentTenant');
        abort_unless($this->isTenantAdmin($tenant), 403, 'Only tenant admins can apply updates.');

        if (! $tenant) {
            $this->dispatch('notify', message: 'No active tenant workspace found.', type: 'error');
            return;
        }

        $appliedPatchIds = collect($tenant->patches_applied ?? [])->map(fn ($id) => (int) $id);
        if ($appliedPatchIds->contains($patchId)) {
            $this->dispatch('notify', message: 'This update was already applied.', type: 'info');
            return;
        }

        $patch = Patch::find($patchId);
        if (! $patch) {
            $this->dispatch('notify', message: 'Selected update was not found.', type: 'error');
            return;
        }

        try {
            $this->runPatchMigrations($tenant, $patch->sql_migrations ?? []);
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('notify', message: 'This update could not be applied. Please contact support.', type: 'error');
            return;
        }

        $updatedPatchIds = $appliedPatchIds
            ->push($patchId)
            ->unique()
            ->values()
            ->all();

        $tenant->update(['patches_applied' => $updatedPatchIds]);

        $this->syncTenantPatchState($tenant, $updatedPatchIds);

        $this->dispatch('notify', message: "Update '{$patch->title}' applied successfully.", type: 'success');
    }

    private function runPatchMigrations($tenant, array $sqlMigrations): void
    {
        if ($sqlMigrations === []) {
            return;
        }

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        foreach ($sqlMigrations as $statement) {
            $statement = trim((string) $statement);

            if ($statement === '') {
                continue;
            }

            DB::connection('tenant')->unprepared($statement);
        }
    }

    private function syncTenantPatchState($tenant, array $patchIds): void
    {
        if (! $tenant->database) {
            return;
        }

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        if (! Schema::connection('tenant')->hasTable('tenants') || ! Schema::connection('tenant')->hasColumn('tenants', 'patches_applied')) {
            return;
        }

        DB::connection('tenant')
            ->table('tenants')
            ->where('id', $tenant->id)
            ->update(['patches_applied' => json_encode($patchIds)]);
    }

    private function isTenantAdmin($tenant): bool
    {
        $user = Auth::user();
        if (! $tenant || ! $user) {
            return false;
        }

        return strcasecmp((string) $user->email, (string) ($tenant->tenant_admin_email ?? '')) === 0;
    }
};
?>

<div class="bg-white min-h-screen">
    <div class="max-w-5xl mx-auto space-y-8">
        <div class="rounded-3xl border border-slate-200 px-8 py-10 shadow-xl" style="background-color: color-mix(in srgb, var(--tenant-secondary) 88%, white 12%); border-color: color-mix(in srgb, var(--tenant-primary) 18%, white 82%);">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em]" style="color: color-mix(in srgb, var(--tenant-primary) 82%, black 18%);">Update Center</p>
                    <h1 class="mt-3 text-3xl font-black tracking-tight text-slate-900">Choose when your workspace takes new patches</h1>
                    <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-600">
                        New patches published by the platform team appear here first. Your tenant admin decides when to apply them.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-3 text-center">
                    <div class="rounded-2xl bg-white px-5 py-4" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 18%, white 82%);">
                        <div class="text-3xl font-black text-slate-900">{{ $pendingCount }}</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Pending</div>
                    </div>
                    <div class="rounded-2xl bg-white px-5 py-4" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 18%, white 82%);">
                        <div class="text-3xl font-black text-slate-900">{{ count($appliedPatchIds) }}</div>
                        <div class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Applied</div>
                    </div>
                </div>
            </div>
        </div>

        @if(! $canApplyUpdates)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-900">
                Only the tenant admin can apply patches for this tenant.
            </div>
        @endif

        <div class="space-y-5">
            @forelse($patches as $patch)
                @php
                    $isApplied = in_array($patch->id, $appliedPatchIds, true);
                    $sqlCount = count($patch->sql_migrations ?? []);
                @endphp
                <div
                    class="rounded-3xl border p-6 shadow-sm {{ $isApplied ? '' : 'border-slate-200 bg-white' }}"
                    style="{{ $isApplied ? 'border-color: color-mix(in srgb, var(--tenant-primary) 20%, white 80%); background-color: color-mix(in srgb, var(--tenant-secondary) 86%, white 14%);' : '' }}"
                >
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <h2 class="text-xl font-bold text-slate-900">{{ $patch->title }}</h2>
                                <span
                                    class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide {{ $isApplied ? '' : 'bg-amber-100 text-amber-700' }}"
                                    style="{{ $isApplied ? 'background-color: color-mix(in srgb, var(--tenant-primary) 12%, white 88%); color: color-mix(in srgb, var(--tenant-primary) 84%, black 16%);' : '' }}"
                                >
                                    {{ $isApplied ? 'Applied' : 'Pending' }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $patch->created_at->format('M d, Y') }}
                                </span>
                            </div>

                            <p class="text-sm leading-6 text-slate-600">{{ $patch->description }}</p>

                            <div class="flex flex-wrap gap-3 text-xs font-semibold text-slate-500">
                                <span class="rounded-full bg-slate-100 px-3 py-1">{{ $sqlCount }} SQL step{{ $sqlCount === 1 ? '' : 's' }}</span>
                                <span class="rounded-full bg-slate-100 px-3 py-1">Patch ID #{{ $patch->id }}</span>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 lg:min-w-44">
                            @if($isApplied)
                                <button type="button" disabled class="rounded-2xl bg-nsync-green-600 px-5 py-3 text-sm font-bold text-white shadow-sm">
                                    Applied
                                </button>
                            @elseif($canApplyUpdates)
                                <button
                                    wire:click="applyPatch({{ $patch->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="applyPatch({{ $patch->id }})"
                                    class="rounded-2xl bg-nsync-green-600 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-nsync-green-700"
                                >
                                    Apply Update
                                </button>
                            @else
                                <button type="button" disabled class="rounded-2xl bg-slate-300 px-5 py-3 text-sm font-bold text-white">
                                    Tenant Admin Required
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-8 py-16 text-center">
                    <h2 class="text-xl font-bold text-slate-900">No patches published yet</h2>
                    <p class="mt-2 text-sm text-slate-500">When the platform team releases optional updates, they will appear here for your review.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
