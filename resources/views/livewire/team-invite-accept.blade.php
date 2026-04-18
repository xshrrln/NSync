<?php
use Livewire\Volt\Component;
use App\Models\PendingInvite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

new class extends Component {
    public $token;
    public $name = '';
    public $password = '';
    public $password_confirmation = '';
    public $invite;

    public function mount($token) {
        $this->token = $token;
        $this->invite = PendingInvite::where('token', $token)
            ->whereHas('tenant')
            ->first();
        if (!$this->invite || $this->invite->hasExpired()) {
            abort(404, 'Invite expired or invalid');
        }
    }

    public function accept() {
        $this->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|min:8|confirmed',
        ]);

        $tenantId = $this->invite->tenant_id;
        $existingUser = User::withoutGlobalScopes()->where('email', $this->invite->email)->first();

        if ($existingUser && $existingUser->tenant_id && (int) $existingUser->tenant_id !== (int) $tenantId) {
            $this->addError('name', 'This email already belongs to another organization account.');
            return;
        }

        if ($existingUser) {
            $existingUser->update([
                'name' => $this->name,
                'password' => bcrypt($this->password),
                'tenant_id' => $tenantId,
                'email_verified_at' => now(),
            ]);
            $user = $existingUser;
        } else {
            $user = User::create([
                'name' => $this->name,
                'email' => $this->invite->email,
                'password' => bcrypt($this->password),
                'tenant_id' => $tenantId,
                'email_verified_at' => now(),
            ]);
        }

        $user->syncRoles([$this->invite->role]);

        $this->syncTenantDatabaseUser($user);

        $this->invite->delete();

        Auth::login($user);

        session()->flash('message', 'Welcome to the team!');
        return redirect()->route('dashboard');
    }

    public function with() {
        return [
            'inviteRole' => $this->invite?->role ?? '',
        ];
    }

    private function syncTenantDatabaseUser(User $centralUser): void
    {
        $tenant = $this->invite?->tenant;
        if (!$tenant?->database) {
            return;
        }

        Config::set('database.connections.tenant', array_merge(
            Config::get('database.connections.mysql'),
            ['database' => $tenant->database]
        ));
        DB::purge('tenant');

        if (!Schema::connection('tenant')->hasTable('users')) {
            return;
        }

        $payload = [
            'name' => $centralUser->name,
            'email' => $centralUser->email,
            'password' => $centralUser->password,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::connection('tenant')->hasColumn('users', 'tenant_id')) {
            $payload['tenant_id'] = $tenant->id;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'role')) {
            $payload['role'] = $this->invite->role === 'Team Supervisor' ? 'supervisor' : 'member';
        }

        if (Schema::connection('tenant')->hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = now();
        }

        DB::connection('tenant')->table('users')->updateOrInsert(
            ['email' => $centralUser->email],
            $payload
        );
    }
}; ?>

<x-guest-layout>
        <div class="w-full">
            <div class="mb-10 text-center">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Accept Your Invite</h2>
                <p class="mt-2 text-sm text-gray-600">Join {{ $invite->tenant->name }} as {{ ucfirst(str_replace('_', ' ', $inviteRole)) }}.</p>
            </div>

            <form wire:submit="accept" class="space-y-6">
                <div class="space-y-2">
                    <label for="email" class="block text-sm font-semibold text-gray-700">Email Address</label>
                    <input type="email" id="email" value="{{ $invite->email }}" class="w-full rounded-xl border border-gray-300 bg-gray-50 px-4 py-3 text-base text-gray-600 shadow-sm outline-none" readonly>
                </div>

                <div class="space-y-2">
                    <label for="name" class="block text-sm font-semibold text-gray-700">Full Name</label>
                    <input type="text" wire:model="name" id="name" placeholder="Enter your full name" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base text-gray-900 placeholder-gray-500 shadow-sm outline-none transition-all focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                    @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="password" class="block text-sm font-semibold text-gray-700">Password</label>
                    <input type="password" wire:model="password" id="password" placeholder="Create password" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base text-gray-900 placeholder-gray-500 shadow-sm outline-none transition-all focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                    @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-2">
                    <label for="password_confirmation" class="block text-sm font-semibold text-gray-700">Confirm Password</label>
                    <input type="password" wire:model="password_confirmation" id="password_confirmation" placeholder="Confirm password" class="w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-base text-gray-900 placeholder-gray-500 shadow-sm outline-none transition-all focus:border-transparent focus:ring-2" style="--tw-ring-color: var(--tenant-primary);">
                </div>

                <button type="submit" class="w-full rounded-xl bg-green-600 py-3.5 text-sm font-bold text-white shadow-md transition-all hover:bg-green-700 active:scale-[0.98]">
                    Accept Invite & Join Workspace
                </button>
            </form>
        </div>
</x-guest-layout>
