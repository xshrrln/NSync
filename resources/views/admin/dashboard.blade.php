@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    <div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Platform Overview</h1>
        <p class="text-gray-500 font-medium mt-1">Global statistics and recent activity.</p>
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
        <a href="{{ app(\App\Support\GitHubReleaseService::class)->repositoryUrl() . '/releases' }}" target="_blank" rel="noopener" class="rounded-2xl border border-green-200 bg-green-50 px-6 py-5 transition hover:bg-green-100">
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
