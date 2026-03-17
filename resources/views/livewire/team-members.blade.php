<?php
use Livewire\Volt\Component;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $search = '';
    public $inviteEmail = '';
    public $selectedRole = 'Team Contributor';

    public function with() {
        return [
            'members' => User::where('tenant_id', app('currentTenant')?->id)
                ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%'))
                ->get(),
            'roles' => ['Team Supervisor', 'Team Contributor'],
            'tenant' => app('currentTenant'),
        ];
    }

    public function invite() {
        $this->validate(['inviteEmail' => 'required|email|unique:users,email']);
        
        // Create user and assign role
        $user = User::create([
            'name' => explode('@', $this->inviteEmail)[0],
            'email' => $this->inviteEmail,
            'password' => bcrypt('password'),
            'tenant_id' => app('currentTenant')?->id,
            'email_verified_at' => now(),
        ]);
        
        $user->assignRole($this->selectedRole);
        
        $this->inviteEmail = '';
        session()->flash('message', 'User invited successfully!');
    }

    public function remove($userId) {
        $user = User::where('id', $userId)->where('tenant_id', app('currentTenant')?->id)->first();
        if ($user && $user->id !== Auth::id()) {
            $user->delete();
        }
    }
}; ?>

<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-900">Team Members</h2>
                <p class="text-gray-600 mt-1">{{ $tenant->users->count() }} members</p>
            </div>
            <div class="flex gap-3">
                <div class="flex">
                    <input wire:model.live="inviteEmail" placeholder="user@example.com" class="px-4 py-2 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500">
                    <select wire:model="selectedRole" class="border border-l-0 border-gray-300 rounded-r-lg px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        @foreach($roles as $role)
                            <option value="{{ $role }}">{{ $role }}</option>
                        @endforeach
                    </select>
                </div>
                <button wire:click="invite" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700">Invite</button>
            </div>
        </div>

        <!-- Members List -->
        <div class="bg-white rounded-xl shadow-sm border p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                            <th class="px-6 py-3 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($members as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white font-bold text-sm">
                                            {{ strtoupper(substr($user->name, 0, 2)) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                                            <div class="text-sm text-gray-500">{{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $user->hasRole('Team Supervisor') ? 'bg-purple-100 text-purple-800' : 'bg-green-100 text-green-800' }}">
                                        {{ $user->getRoleNames()->first() ?? 'Contributor' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ $user->created_at->diffForHumans() }}
                                </td>
                                <td class="px-6 py-4 text-right text-sm font-medium">
                                    @if($user->id !== auth()->id())
                                        <button wire:click="remove({{ $user->id }})" class="text-red-600 hover:text-red-900">Remove</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                    <svg class="w-12 h-12 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.128 0M7 13v-1a4 4 0 013.268-3.137 3.998 3.998 0 016.463-.232l.342.574a3.998 3.998 0 006.463.232 4 4 0 013.268 3.137V13M17.29 13.47a12.001 12.001 0 01-1.8 0M12 13a12 12 0 01-1.8 0"/>
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No team members</h3>
                                    <p class="text-sm">Invite your first team member to collaborate</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
