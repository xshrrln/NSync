@extends('layouts.admin')

@section('content')
@php
    $releaseService = app(\App\Support\GitHubReleaseService::class);
    $releases = collect($releases ?? []);
    $latestRelease = $releases->first();
    $releaseFeedStatus = $releaseFeedStatus ?? ['has_releases' => $releases->isNotEmpty(), 'last_error' => null];
@endphp
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Platform Overview</h1>
        <p class="text-gray-500 font-medium mt-1">Global statistics, release history, and past central activity.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-base font-bold text-gray-400 uppercase tracking-widest">Total Tenants</p>
            <p class="text-3xl font-black text-gray-900 mt-1">{{ $tenantsCount }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-sm font-bold text-amber-600 uppercase tracking-widest">Pending</p>
            <p class="text-3xl font-black text-gray-900 mt-1">{{ $pendingCount }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-sm font-bold text-green-600 uppercase tracking-widest">Active</p>
            <p class="text-3xl font-black text-gray-900 mt-1">{{ $activeCount }}</p>
        </div>
        <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
            <p class="text-sm font-bold text-red-600 uppercase tracking-widest">Suspended</p>
            <p class="text-3xl font-black text-gray-900 mt-1">{{ $suspendedCount }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <a href="{{ $releaseService->repositoryUrl() . '/releases' }}" target="_blank" rel="noopener" class="rounded-2xl border border-green-200 bg-green-50 px-6 py-5 transition hover:bg-green-100">
            <p class="text-xs font-bold uppercase tracking-widest text-green-700">Central Updates</p>
            <h3 class="mt-1 text-lg font-black text-gray-900">GitHub Releases</h3>
            <p class="mt-1 text-sm text-gray-600">Create releases in GitHub so tenant workspaces receive the new version feed.</p>
        </a>
        <a href="{{ route('admin.support.index') }}" class="rounded-2xl border border-slate-200 bg-slate-50 px-6 py-5 transition hover:bg-slate-100">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-600">Tenant Assistance</p>
            <h3 class="mt-1 text-lg font-black text-gray-900">Open Support Desk</h3>
            <p class="mt-1 text-sm text-gray-600">Review all support tickets, reply, and close issues quickly.</p>
        </a>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <section class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-50 flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-green-700">Updates Moved Here</p>
                    <h3 class="mt-1 text-lg font-black text-gray-900">Release History</h3>
                    <p class="mt-1 text-sm text-gray-600">Latest published versions and their release notes.</p>
                </div>
                <a href="{{ $releaseService->repositoryUrl() . '/releases' }}" target="_blank" rel="noopener" class="text-sm font-bold text-green-600 hover:underline">Open GitHub</a>
            </div>

            <div class="p-6 space-y-4">
                @if(!$releaseFeedStatus['has_releases'] && $releaseFeedStatus['last_error'])
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                        Release history could not be loaded from GitHub right now. Last fetch error: {{ $releaseFeedStatus['last_error'] }}
                    </div>
                @endif

                @if($latestRelease)
                    <div class="rounded-2xl border border-green-100 bg-green-50 px-5 py-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-black text-gray-900">{{ $latestRelease['name'] }}</h4>
                            <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-green-700">{{ $latestRelease['tag_name'] }}</span>
                            @if($latestRelease['published_on'])
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600">{{ $latestRelease['published_on'] }}</span>
                            @endif
                        </div>
                        <p class="mt-3 text-sm leading-6 text-gray-600">{{ $latestRelease['body'] ?: 'Maintenance and reliability updates included in this release.' }}</p>
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse($releases as $release)
                        <div class="rounded-2xl border border-slate-200 px-5 py-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-gray-900">{{ $release['name'] }}</p>
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $release['tag_name'] }}</span>
                                @if($release['published_on'])
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">{{ $release['published_on'] }}</span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-6 text-gray-600">{{ $release['body'] ?: 'Maintenance and reliability updates included in this release.' }}</p>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 px-6 py-12 text-center text-sm text-gray-500">
                            No release history is available yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-8 py-6 border-b border-gray-50 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-slate-500">Past Logs</p>
                    <h3 class="mt-1 text-lg font-black text-gray-900">Central Audit Trail</h3>
                    <p class="mt-1 text-sm text-gray-600">Recent admin and tenant-related system events captured centrally.</p>
                </div>
                <a href="{{ route('admin.audit-trail.export') }}" class="inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                    Download PDF
                </a>
            </div>

            @if($adminAuditLogs->isEmpty())
                <div class="px-8 py-12 text-sm text-gray-500">No audit entries yet.</div>
            @else
                <div class="divide-y divide-slate-100">
                    @foreach($adminAuditLogs as $log)
                        @php
                            $requestLine = collect([$log->method, $log->path])->filter()->implode(' ');
                            $audience = (string) data_get($log->context, 'audience', 'system');
                            $workspaceLine = collect([
                                data_get($log->context, 'tenant_name'),
                                data_get($log->context, 'tenant_domain'),
                            ])->filter()->implode(' | ');
                        @endphp
                        <article class="px-8 py-5">
                            <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="font-bold text-gray-900">{{ $log->action }}</p>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $audience === 'tenant' ? 'bg-sky-50 text-sky-700' : 'bg-emerald-50 text-emerald-700' }}">
                                            {{ $audience === 'tenant' ? 'Tenant' : 'Admin' }}
                                        </span>
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold {{ ($log->status_code ?? 500) < 400 ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                            {{ $log->status_code ?? 'N/A' }}
                                        </span>
                                    </div>
                                    @if($log->description)
                                        <p class="mt-2 text-sm leading-6 text-gray-600">{{ $log->description }}</p>
                                    @endif
                                    <div class="mt-3 space-y-1 text-xs text-gray-500">
                                        <p>{{ optional($log->occurred_at)->format('M d, Y h:i:s A') ?: 'Unknown time' }}</p>
                                        <p>{{ $log->user_name ?: 'System' }}{{ $log->user_email ? ' | ' . $log->user_email : '' }}</p>
                                        @if($requestLine !== '')
                                            <p>{{ $requestLine }}</p>
                                        @endif
                                        @if($workspaceLine !== '')
                                            <p>Workspace: {{ $workspaceLine }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-8 py-6 border-b border-gray-50 flex justify-between items-center">
            <h3 class="text-lg font-bold text-gray-900">Recent Registrations</h3>
            <a href="{{ route('admin.tenants.index') }}" class="text-sm font-bold text-green-600 hover:underline">View All</a>
        </div>
        <div class="p-4">
            @php
                $recentTenants = \App\Models\Tenant::latest()->take(5)->get();
            @endphp
            <div class="space-y-3">
                @foreach($recentTenants as $tenant)
                <div class="flex items-center justify-between p-4 rounded-2xl hover:bg-gray-50 transition-colors border border-transparent hover:border-gray-100">
                    <div class="flex items-center gap-4">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center text-green-700 font-bold text-xs">
                            {{ strtoupper(substr($tenant->name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-bold text-gray-900 text-sm">{{ $tenant->name }}</p>
                            <p class="text-gray-400 text-xs">{{ $tenant->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    <span class="px-3 py-1 rounded-full text-[10px] font-bold uppercase 
                        {{ $tenant->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ $tenant->status }}
                    </span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
