<?php

use Livewire\Volt\Component;
use App\Models\Tenant;
use App\Http\Controllers\TenantController;

new class extends Component {
    public function approve(TenantController $controller, Tenant $tenant)
    {
        $controller->approve($tenant);
        $this->dispatch('tenant-approved');
    }
}; ?>

<div>
    <div class="bg-white shadow-xl rounded-2xl p-8">
        <h2 class="text-3xl font-bold text-gray-900 mb-8">Pending Workspace Approvals</h2>
        
@if($tenants->where('status', 'pending')->count() === 0)
            <div class="text-center py-12">
                <div class="w-20 h-20 bg-green-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No pending approvals</h3>
                <p class="text-gray-500">All workspaces are approved and active.</p>
            </div>
        @else
            <div class="grid gap-6">
@foreach($tenants as $tenant)
    <tr class="border-b border-gray-200 hover:bg-gray-50">
        <td class="p-4 font-medium text-gray-900">{{ $tenant->name }}</td>
        <td class="p-4 text-gray-600">{{ $tenant->tenant_admin ?? 'N/A' }}</td>
text-base text-gray-500">{{ $tenant->tenant_admin_email }}</td>
        <td class="p-4">
text-{{ $tenant->status === 'approved' ? 'green' : 'yellow' }}-800 text-sm font-medium rounded-full">
                {{ ucfirst($tenant->status) }}
            </span>
        </td>
text-base font-medium">{{ $tenant->member_count }} members</td>
</xai:function_call >

<xai:function_call name="edit_file">
<parameter name="path">resources/views/components/admin/⚡tenant-approval.blade.php
        <td class="p-4 text-sm text-gray-500">{{ number_format($tenant->storage_used_kb, 1) }} KB</td>
        <td class="p-4 text-sm font-medium">{{ $tenant->plan }}</td>
        <td class="p-4 text-right">
            <form method="POST" action="{{ route('admin.tenants.approve', $tenant) }}" class="inline">
                @csrf @method('PATCH')
                <button type="submit" class="text-green-600 hover:text-green-900 font-medium">Approve</button>
            </form>
            <a href="#" class="text-gray-600 hover:text-gray-900 ml-4 font-medium">View</a>
        </td>
    </tr>
@endforeach
<tfoot>
    <tr>
        <td colspan="8">
            {{ $tenants->links() }}
        </td>
    </tr>
</tfoot>
                    <div class="border border-gray-200 rounded-xl p-6 hover:shadow-lg transition-all">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h4 class="text-xl font-bold text-gray-900">{{ $tenant->name }}</h4>
                                <p class="text-gray-500">{{ $tenant->domain }}.com</p>
                            </div>
                            <span class="px-4 py-1 bg-yellow-100 text-yellow-800 text-sm font-medium rounded-full">Pending</span>
                        </div>
                        <div class="mb-6">
                            <p class="text-sm text-gray-500 mb-4">Owner: {{ $tenant->users->first()?->name ?? 'N/A' }}</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($tenant->users as $user)
                                    <span class="px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded-full">{{ $user->name }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button wire:click="approve(tenantController, {{ $tenant->id }})" class="flex-1 bg-blue-600 text-white py-2 px-4 rounded-lg font-medium hover:bg-blue-700 transition">
                                Approve Workspace
                            </button>
                            <button class="flex-1 bg-gray-100 text-gray-700 py-2 px-4 rounded-lg font-medium hover:bg-gray-200 transition">
                                View Details
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
