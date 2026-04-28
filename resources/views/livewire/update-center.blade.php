<?php

use App\Support\GitHubReleaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Volt\Component;

new class extends Component {
    public function with(): array
    {
        $releaseService = app(GitHubReleaseService::class);
        $releases = collect($releaseService->releases(12));
        $tenant = app('currentTenant');
        $appliedVersion = $tenant?->applied_release_version;
        $latestRelease = $releases->first();
        $appliedIndex = $releases->search(fn ($release) => $release['tag_name'] === $appliedVersion);

        return [
            'tenant' => $tenant,
            'latestRelease' => $latestRelease,
            'latestVersion' => $releaseService->latestVersion(),
            'appliedVersion' => $appliedVersion,
            'appliedVersionDisplay' => $appliedVersion ?: 'Not applied',
            'canApplyReleases' => $this->canApplyReleases($tenant),
            'pendingReleaseCount' => $appliedIndex === false ? $releases->count() : $appliedIndex,
            'releases' => $releases,
        ];
    }

    public function applyRelease(string $tagName): void
    {
        $tenant = app('currentTenant');

        abort_unless($this->canApplyReleases($tenant), 403, 'Only tenant admins and workspace supervisors can apply releases.');

        $release = collect(app(GitHubReleaseService::class)->releases(30))
            ->firstWhere('tag_name', $tagName);

        if (! $tenant || ! $release) {
            $this->dispatch('notify', message: 'Selected release was not found.', type: 'error');
            return;
        }

        $tenant->forceFill([
            'applied_release_version' => $release['tag_name'],
            'applied_release_at' => now(),
        ])->save();

        $this->syncTenantReleaseState($tenant, $release['tag_name']);

        $this->dispatch('notify', message: "Release {$release['tag_name']} applied to this workspace.", type: 'success');
    }

    private function canApplyReleases($tenant): bool
    {
        $user = Auth::user();
        if (! $tenant || ! $user) {
            return false;
        }

        if (strcasecmp((string) $user->email, (string) ($tenant->tenant_admin_email ?? '')) === 0) {
            return true;
        }

        return $user->hasRole('Team Supervisor');
    }

    private function syncTenantReleaseState($tenant, string $tagName): void
    {
        if (! $tenant->database) {
            return;
        }

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        if (! Schema::connection('tenant')->hasTable('tenants')
            || ! Schema::connection('tenant')->hasColumn('tenants', 'applied_release_version')
            || ! Schema::connection('tenant')->hasColumn('tenants', 'applied_release_at')) {
            return;
        }

        DB::connection('tenant')
            ->table('tenants')
            ->where('id', $tenant->id)
            ->update([
                'applied_release_version' => $tagName,
                'applied_release_at' => now(),
            ]);
    }
};
?>

<div class="min-h-screen bg-white">
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between py-4 min-h-[116px]">
                <div>
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Version Updates</h1>
                    <p class="text-gray-600 mb-0">Review the current NSync release and recent product release notes.</p>
                </div>
                <div>
                    <span class="rounded-full border px-3 py-1 text-[10px] font-bold uppercase" style="border-color: color-mix(in srgb, var(--tenant-primary) 25%, white 75%); background-color: color-mix(in srgb, var(--tenant-primary) 10%, white 90%); color: color-mix(in srgb, var(--tenant-primary) 80%, black 20%);">Updates</span>
                </div>
            </div>
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-6 py-8 space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white px-8 py-8 shadow-lg" style="border-color: color-mix(in srgb, var(--tenant-primary) 20%, white 80%);">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-xl font-black tracking-tight" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Release Overview</h2>
                    <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">New releases from the NSync team appear here first. Your workspace can choose when to apply them.</p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Applied Version</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $appliedVersionDisplay }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Latest Available</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $latestVersion }}</p>
                </div>
                <div class="rounded-2xl bg-white px-4 py-3" style="border: 1px solid color-mix(in srgb, var(--tenant-primary) 12%, white 88%);">
                    <p class="text-xs uppercase tracking-wide text-slate-600">Available Updates</p>
                    <p class="mt-1 text-2xl font-black text-slate-900">{{ $pendingReleaseCount }}</p>
                </div>
            </div>
        </div>

        @if(! $canApplyReleases)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-900">
                Only the tenant admin or a workspace supervisor can apply releases for this workspace.
            </div>
        @endif

        @if($latestRelease)
            <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
                <div class="flex flex-col gap-4 border-b border-slate-100 pb-6 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">{{ $latestRelease['name'] }}</h2>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase" style="background-color: color-mix(in srgb, var(--tenant-primary) 13%, white 87%); color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);">{{ $latestRelease['tag_name'] }}</span>
                            @if($latestRelease['published_on'])
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $latestRelease['published_on'] }}</span>
                            @endif
                            @if($latestRelease['is_prerelease'])
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">Prerelease</span>
                            @endif
                        </div>
                    </div>

                    @if($latestRelease['tag_name'] === $appliedVersion)
                        <button type="button" disabled class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-bold text-slate-600">
                            Applied
                        </button>
                    @elseif($canApplyReleases)
                        <button
                            type="button"
                            wire:click="applyRelease('{{ $latestRelease['tag_name'] }}')"
                            wire:loading.attr="disabled"
                            wire:target="applyRelease('{{ $latestRelease['tag_name'] }}')"
                            class="rounded-xl px-4 py-2 text-sm font-bold text-white transition disabled:opacity-60"
                            style="background-color: var(--tenant-primary);"
                        >
                            <span wire:loading.remove wire:target="applyRelease('{{ $latestRelease['tag_name'] }}')">Apply Release</span>
                            <span wire:loading wire:target="applyRelease('{{ $latestRelease['tag_name'] }}')">Applying...</span>
                        </button>
                    @else
                        <button type="button" disabled class="rounded-xl bg-slate-200 px-4 py-2 text-sm font-bold text-slate-600">
                            Admin Required
                        </button>
                    @endif
                </div>

                <div class="mt-6">
                    <h3 class="text-lg font-bold text-slate-900">Latest Release Notes</h3>
                    <p class="mt-3 whitespace-pre-line text-sm leading-6 text-slate-600">{{ $latestRelease['body'] ?: 'Maintenance and reliability updates included in this release.' }}</p>
                </div>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Release History</h2>
                    <p class="mt-1 text-sm text-slate-500">Recent product versions published by the NSync team.</p>
                </div>
            </div>

            <div class="space-y-3">
                @forelse($releases as $release)
                    <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4">
                        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-sm font-bold text-slate-900">{{ $release['name'] }}</h3>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $release['tag_name'] }}</span>
                                    @if($release['tag_name'] === $appliedVersion)
                                        <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase" style="background-color: color-mix(in srgb, var(--tenant-primary) 13%, white 87%); color: color-mix(in srgb, var(--tenant-primary) 85%, black 15%);">Applied</span>
                                    @endif
                                    @if($release['published_on'])
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ $release['published_on'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-slate-600">{{ $release['body'] ?: 'Maintenance and reliability updates included in this release.' }}</p>
                            </div>

                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-14 text-center">
                        <h3 class="text-lg font-bold text-slate-900">No releases found</h3>
                        <p class="mt-2 text-sm text-slate-500">No product releases have been published yet.</p>
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</div>
