<?php
use Livewire\Volt\Component;
use App\Models\Tenant;

new class extends Component {
    public $name = '';
    public $domain = '';

    public function createTenant()
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'domain' => 'required|string|unique:tenants,domain',
        ]);

        $tenant = Tenant::create([
            'name' => $this->name,
            'domain' => $this->domain,
            'plan' => 'free',
            'status' => 'pending',
        ]);

        auth()->user()->update(['tenant_id' => $tenant->id]);

        session()->flash('message', 'Tenant request sent! Awaiting admin approval.');

        $this->reset(['name', 'domain']);
    }
}; ?>

<div>
    <div class="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6">Create Your Workspace</h2>
            <form wire:submit="createTenant">
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Organization Name</label>
                    <input type="text" wire:model="name" class="w-full px-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Acme Corp">
                    @error('name') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Workspace Domain</label>
                    <div class="relative">
                        <input type="text" wire:model="domain" class="w-full pl-12 pr-4 py-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="acme">
                        <span class="absolute left-4 top-3 text-gray-400 text-sm font-mono">yourapp.com/</span>
                    </div>
                    @error('domain') <span class="text-red-500 text-sm mt-1">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="w-full bg-blue-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-blue-700 transition">Request Workspace</button>
            </form>
        </div>
    </div>
</div>

