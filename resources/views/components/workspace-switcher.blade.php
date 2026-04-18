<div class="dropdown" data-bs-toggle="dropdown" aria-expanded="false">
    <button class="flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg transition dropdown-toggle" style="border: none; background: none;">
        <div class="w-8 h-8 bg-gradient-to-br from-nsync-green-500 to-nsync-green-600 rounded-lg flex items-center justify-center text-white text-xs font-bold">
            {{ $currentTenant->name[0] ?? 'W' }}
        </div>
        <span>{{ $currentTenant->name ?? 'No Workspace' }}</span>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>
    
    <ul class="dropdown-menu dropdown-menu-end shadow-2xl border border-gray-200" style="min-width: 20rem; border-radius: 0.75rem; margin-top: 0.5rem;">
        <li>
            <div class="px-4 py-3 border-b border-gray-100 dropdown-header">
                <h4 class="font-semibold text-gray-900 text-sm mb-1">Workspaces</h4>
                <p class="text-xs text-gray-500 mb-0">Switch between organizations</p>
            </div>
        @forelse($tenants as $tenant)
<a wire:click="switchTenant({{ $tenant->id }})" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition cursor-pointer">
    
    <div class="w-10 h-10 bg-gradient-to-br from-nsync-green-500 to-nsync-green-600 rounded-lg flex items-center justify-center text-white text-xs font-bold">
        {{ strtoupper(substr($tenant->name ?? 'W', 0, 1)) }}
    </div>

    <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-900 truncate">{{ $tenant->name ?? 'Unnamed Workspace' }}</p>
        <p class="text-xs text-gray-500 truncate">{{ $tenant->domain ?? 'no-domain' }}.com</p>
    </div>

    <span class="text-xs px-2 py-1 bg-green-100 text-green-800 rounded-full font-medium">
        {{ ucfirst($tenant->plan ?? 'free') }}
    </span>

</a>
@empty
            <div class="px-4 py-6 text-center text-gray-500 text-sm">
                No workspaces yet
                <a href="{{ route('tenant.request') }}" class="block mt-2 text-blue-600 hover:text-blue-500 font-medium">Create one →</a>
            </div>
        @endforelse
    </div>
</div>
