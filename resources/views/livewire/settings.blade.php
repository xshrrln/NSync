<?php
use Livewire\Volt\Component;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

new class extends Component {
    public $email;
    public $name;
    public $current_password;
    public $password;
    public $password_confirmation;
    public $updatingPassword = false;
    public $showDeleteModal = false;

    public function mount() {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfile() {
        $this->validate(['name' => 'required|string|max:255', 'email' => 'required|email|max:255']);
        Auth::user()->update(['name' => $this->name, 'email' => $this->email]);
        $this->dispatch('notify', 'Profile updated!');
    }

    public function updatePassword() {
        $this->validate(['current_password' => 'required|current_password', 'password' => 'required|min:8|confirmed']);
        Auth::user()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation', 'updatingPassword']);
        $this->dispatch('notify', 'Password updated!');
    }

    public function deleteUser() {
        $this->validate(['current_password' => 'required|current_password']);
        $user = Auth::user();
        Auth::logout();
        $user->delete();
        return redirect()->to('/');
    }
}; ?>

<div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
    <!-- Header -->
    <div class="bg-white bg-opacity-80 backdrop-blur-sm border-b border-gray-200 sticky top-0 z-40">
        <div class="max-w-2xl mx-auto px-6 py-8">
            <h1 class="text-3xl font-bold text-gray-900">Settings</h1>
            <p class="text-gray-600 mt-2">Manage your account and preferences</p>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-2xl mx-auto px-6 py-12">
        <div class="space-y-8">
            <!-- Profile Section -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Profile Information</h2>
                    <p class="text-gray-600 text-sm mt-2">Update your personal details</p>
                </div>

                <div class="space-y-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" wire:model="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm" placeholder="Your name" />
                        @error('name') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" wire:model="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm" placeholder="your@email.com" />
                        @error('email') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <button wire:click="updateProfile" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">Save Changes</button>
                </div>
            </div>

            <!-- Password Section -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6 flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Password</h2>
                        <p class="text-gray-600 text-sm mt-2">Change your password to keep your account secure</p>
                    </div>
                    <button wire:click="$toggle('updatingPassword')" class="text-blue-600 hover:text-blue-900 text-sm font-semibold transition">
                        {{ $updatingPassword ? 'Cancel' : 'Change Password' }}
                    </button>
                </div>

                @if($updatingPassword)
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                            <input type="password" wire:model="current_password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm" placeholder="Enter current password" />
                            @error('current_password') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" wire:model="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm" placeholder="Enter new password" />
                            @error('password') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" wire:model="password_confirmation" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition text-sm" placeholder="Confirm new password" />
                        </div>

                        <button wire:click="updatePassword" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition">Update Password</button>
                    </div>
                @endif
            </div>

            <!-- Danger Zone -->
            <div class="bg-red-50 shadow-sm rounded-lg border border-red-200 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-red-900">Danger Zone</h2>
                    <p class="text-red-700 text-sm mt-2">Irreversible and destructive actions</p>
                </div>

                <div class="p-6 bg-red-100 bg-opacity-30 rounded-lg border border-red-300 mb-6">
                    <h3 class="font-semibold text-red-900 mb-2">Delete Account</h3>
                    <p class="text-sm text-red-700 mb-4">Once you delete your account, there is no going back. Please be certain.</p>
                    <button wire:click="$set('showDeleteModal', true)" class="px-6 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    @if($showDeleteModal)
        <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg shadow-xl max-w-sm w-full p-8">
                <div class="mb-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 bg-red-100 rounded-lg mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 4v2m-6-4a9 9 0 1118 0 9 9 0 01-18 0z"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">Delete Account?</h2>
                    <p class="text-gray-600 text-sm mt-2">This action cannot be undone. Enter your password to confirm.</p>
                </div>

                <div class="mb-6">
                    <input type="password" wire:model="current_password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent text-sm" placeholder="Enter your password" />
                </div>

                <div class="flex gap-3">
                    <button wire:click="$set('showDeleteModal', false)" class="flex-1 px-4 py-2 text-gray-700 bg-gray-100 font-medium rounded-lg hover:bg-gray-200 transition text-sm">Cancel</button>
                    <button wire:click="deleteUser" class="flex-1 px-4 py-2 bg-red-600 text-white font-medium rounded-lg hover:bg-red-700 transition text-sm">Delete</button>
                </div>
            </div>
        </div>
    @endif
</div>