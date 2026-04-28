@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-3xl font-black text-gray-900 tracking-tight">Organization Directory</h1>
        <p class="text-gray-500 font-medium mt-1">Full management and monitoring of all platform tenants.</p>
    </div>

    <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-8 py-6 bg-gray-50/50 border-b border-gray-100 flex flex-col md:flex-row md:items-center gap-4">
            <h3 class="font-bold text-gray-900 shrink-0">All Organizations</h3>

            <div class="flex items-center gap-3 ml-auto">
                <div class="relative">
                    <input type="text" id="orgSearch" placeholder="Search..." class="w-64 pl-10 pr-4 py-2 border border-gray-200 rounded-xl bg-white text-sm focus:ring-2 focus:ring-green-500 outline-none">
                    <svg class="w-4 h-4 absolute left-3 top-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </div>
                <select id="statusFilter" class="pl-4 pr-10 py-2 border border-gray-200 rounded-xl bg-white text-sm font-bold focus:ring-2 focus:ring-green-500 outline-none">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="pending">Pending</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-400 uppercase text-[10px] font-bold tracking-widest border-b border-gray-100">
                    <tr>
                        <th class="px-8 py-5">Organization & Domain</th>
                        <th class="px-8 py-5">Address</th>
                        <th class="px-8 py-5">Tenant Admin</th>
                        <th class="px-8 py-5 text-center">Plan</th>
                        <th class="px-8 py-5 text-center">Members</th>
                        <th class="px-8 py-5 text-center">Workspace Size</th>
                        <th class="px-8 py-5 text-center">Dates</th>
                        <th class="px-8 py-5 text-center">Status</th>
                        <th class="px-8 py-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($tenants as $tenant)
                    <tr class="hover:bg-green-50/30 transition-colors group tenant-row"
                        data-status="{{ strtolower($tenant->status) }}"
                        data-search="{{ strtolower($tenant->name . ' ' . $tenant->domain . ' ' . ($tenant->tenant_admin ?? '') . ' ' . ($tenant->tenant_admin_email ?? '')) }}">
                        <td class="px-8 py-5">
                            <div class="flex items-center gap-4">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-white font-bold text-xs shadow-sm" style="background: var(--tenant-primary);">
                                    {{ strtoupper(substr($tenant->name, 0, 1)) }}
                                </div>
                                <div>
                                    <p class="font-bold text-gray-900 leading-none">{{ $tenant->name }}</p>
                                    <a href="http://{{ $tenant->domain }}:8000"
                                       target="_blank"
                                       class="text-green-600 font-mono text-[10px] mt-1 inline-block hover:underline">
                                        {{ $tenant->domain }}
                                    </a>
                                </div>
                            </div>
                        </td>
                        <td class="px-8 py-5 text-gray-500 max-w-[150px] truncate">{{ $tenant->address ?? 'N/A' }}</td>
                        <td class="px-8 py-5">
                            <p class="font-bold text-gray-900">{{ $tenant->tenant_admin }}</p>
                            <p class="text-gray-400 text-xs">{{ $tenant->tenant_admin_email }}</p>
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="px-2 py-1 rounded-lg text-[10px] font-black uppercase {{ $tenant->plan === 'pro' ? 'bg-purple-50 text-purple-600' : 'bg-gray-100 text-gray-600' }}">
                                {{ $tenant->plan }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-center font-bold text-gray-700">{{ $tenant->member_count }}</td>
                        <td class="px-8 py-5 text-center text-gray-500 font-medium">{{ number_format($tenant->storage_used_kb, 1) }} KB</td>
                        <td class="px-8 py-5 text-center text-[11px] text-gray-500">
                            {{ $tenant->start_date ? \Carbon\Carbon::parse($tenant->start_date)->format('M d, Y') : '-' }}
                        </td>
                        <td class="px-8 py-5 text-center">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold uppercase
                                {{ $tenant->status === 'active' ? 'bg-green-50 text-green-700' : ($tenant->status === 'disabled' ? 'bg-orange-50 text-orange-700' : 'bg-amber-50 text-amber-700') }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $tenant->status === 'active' ? 'bg-green-500' : ($tenant->status === 'disabled' ? 'bg-orange-500' : 'bg-amber-500') }}"></span>
                                {{ $tenant->status }}
                            </span>
                        </td>
                        <td class="px-8 py-5 text-right">
                            <div class="flex flex-col sm:flex-row justify-end gap-2">
                                @if($tenant->status === 'pending')
                                    <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" class="inline"
                                          data-confirm-modal="true"
                                          data-confirm-title="Approve Workspace"
                                          data-confirm-message="Approve {{ $tenant->name }} and send workspace credentials to {{ $tenant->tenant_admin_email }}?"
                                          data-confirm-button="Approve">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-green-700 bg-green-50 hover:bg-green-100 rounded-md transition-all">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.tenants.reject', $tenant) }}" class="inline"
                                          data-confirm-modal="true"
                                          data-confirm-title="Reject Workspace"
                                          data-confirm-message="Reject {{ $tenant->name }} registration request?"
                                          data-confirm-button="Reject">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-red-700 bg-red-50 hover:bg-red-100 rounded-md transition-all">
                                            Reject
                                        </button>
                                    </form>
                                @elseif($tenant->status === 'active')
                                    <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" class="inline"
                                          data-confirm-modal="true"
                                          data-confirm-title="Suspend Workspace"
                                          data-confirm-message="Suspend {{ $tenant->name }} now? Members will lose access until resumed."
                                          data-confirm-button="Suspend">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-orange-700 bg-orange-50 hover:bg-orange-100 rounded-md transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                            Suspend
                                        </button>
                                    </form>
                                @elseif($tenant->status === 'disabled')
                                    <form method="POST" action="{{ route('admin.tenants.resume', $tenant) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-bold text-green-700 bg-green-50 hover:bg-green-100 rounded-md transition-all">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            Resume
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('admin.tenants.edit', $tenant) }}" class="inline-flex items-center gap-2 px-3 py-2 text-sm font-semibold text-green-700 bg-green-50 hover:bg-green-100 rounded-lg transition-all shadow-sm">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    Manage
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-8 py-20 text-center text-gray-400 font-medium">No organizations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    const searchInput = document.getElementById('orgSearch');
    const statusFilter = document.getElementById('statusFilter');
    const rows = document.querySelectorAll('.tenant-row');

    function applyTenantFilters() {
        const searchTerm = (searchInput?.value || '').toLowerCase().trim();
        const selectedStatus = (statusFilter?.value || '').toLowerCase().trim();

        rows.forEach((row) => {
            const haystack = (row.dataset.search || '').toLowerCase();
            const rowStatus = (row.dataset.status || '').toLowerCase();

            const matchesSearch = !searchTerm || haystack.includes(searchTerm);
            const matchesStatus = !selectedStatus || rowStatus === selectedStatus;

            row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyTenantFilters);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', applyTenantFilters);
    }
</script>
@endsection
