<?php
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

new class extends Component {
    use WithFileUploads;

    public $email;
    public $name;
    public $avatar;
    public $avatarUrl = null;
    public $current_password;
    public $password;
    public $password_confirmation;
    public $updatingPassword = false;
    public $showDeleteModal = false;
    public $primaryColor = '#16A34A';
    public $secondaryColor = '#FFFFFF';
    public $updatingTheme = false;
    public $updatingTwoFactor = false;
    public bool $twoFactorEnabled = false;
    public string $twoFactorScope = 'all_members';
    public string $twoFactorFrequency = 'new_device';
    public int $twoFactorCodeTtlMinutes = 10;
    public $primaryOptions = ['#16A34A', '#34D399', '#60A5FA', '#FBBF24', '#F472B6', '#F87171', '#9CA3AF'];
    public $secondaryOptions = ['#FFFFFF', '#F8FAFC', '#ECFEFF', '#FFF7ED', '#FEF2F2', '#F3F4F6'];

    private function ensureSubscriptionAccess(string $title = 'Subscription Required'): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $tenant->requiresSubscriptionRenewal()) {
            return true;
        }

        $this->dispatch('subscription-expired', title: $title, message: $tenant->subscriptionLockMessage());

        return false;
    }

    private function canUploadProfileAvatar(): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        // Non-tenant contexts are not restricted by tenant plan features.
        if (! $tenant) {
            return true;
        }

        return $tenant->hasFeature('file-attachments');
    }

    public function mount() {
        $this->primaryOptions = $this->normalizeHexOptions(
            AppSetting::get('theme_primary_options', $this->primaryOptions),
            $this->primaryOptions
        );
        $this->secondaryOptions = $this->normalizeHexOptions(
            AppSetting::get('theme_secondary_options', $this->secondaryOptions),
            $this->secondaryOptions
        );

        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->avatarUrl = Auth::user()->avatar;
        
        // Get tenant theme if exists
        if (app()->has('currentTenant')) {
            $tenant = app('currentTenant');
            $theme = $tenant->theme;
            $this->primaryColor = in_array($theme['primary'] ?? '', $this->primaryOptions, true)
                ? $theme['primary']
                : $this->primaryOptions[0];
            $this->secondaryColor = in_array($theme['secondary'] ?? '', $this->secondaryOptions, true)
                ? $theme['secondary']
                : $this->secondaryOptions[0];

            if ($tenant->hasFeature('two-factor')) {
                $twoFactor = $tenant->twoFactorSettings();
                $this->twoFactorEnabled = (bool) ($twoFactor['enabled'] ?? false);
                $this->twoFactorScope = $twoFactor['scope'] ?? 'all_members';
                $this->twoFactorFrequency = $twoFactor['frequency'] ?? 'new_device';
                $this->twoFactorCodeTtlMinutes = (int) ($twoFactor['code_ttl_minutes'] ?? 10);
            }
        }
    }

    public function updateProfile() {
        if ($this->avatar && ! $this->canUploadProfileAvatar()) {
            $this->reset('avatar');
            $this->addError('avatar', 'Profile photo upload is available on Standard and Pro plans only.');
            return;
        }

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'avatar' => 'nullable|image|max:2048',
        ], [
            'avatar.uploaded' => 'Avatar upload did not complete. Please wait for upload to finish, then try again.',
            'avatar.image' => 'The profile photo must be a valid image.',
            'avatar.max' => 'The profile photo must not exceed 2MB.',
        ]);

        $user = Auth::user();
        $payload = [
            'name' => $this->name,
            'email' => $this->email,
        ];

        if ($this->avatar) {
            $existingAvatar = (string) ($user->avatar ?? '');
            if ($existingAvatar !== '' && str_starts_with($existingAvatar, 'avatars/')) {
                Storage::disk('public')->delete($existingAvatar);
            }

            $path = $this->avatar->store('avatars', 'public');
            $payload['avatar'] = $path;
            $this->avatarUrl = $path;
            $this->reset('avatar');
        }

        $user->update($payload);
        $this->dispatch('notify', 'Profile updated!');
    }

    public function updatePassword() {
        $this->validate(['current_password' => 'required|current_password', 'password' => 'required|min:8|confirmed']);
        Auth::user()->update(['password' => Hash::make($this->password)]);
        $this->reset(['current_password', 'password', 'password_confirmation', 'updatingPassword']);
        $this->dispatch('notify', 'Password updated!');
    }

    public function updateTheme() {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!in_array($this->primaryColor, $this->primaryOptions, true)) {
            $this->primaryColor = $this->primaryOptions[0] ?? '#16A34A';
        }
        if (!in_array($this->secondaryColor, $this->secondaryOptions, true)) {
            $this->secondaryColor = $this->secondaryOptions[0] ?? '#FFFFFF';
        }

        if (app()->has('currentTenant')) {
            $tenant = app('currentTenant');
            $tenant->update([
                'theme' => json_encode([
                    'primary' => $this->primaryColor,
                    'secondary' => $this->secondaryColor,
                ])
            ]);
            $this->updatingTheme = false;
            $this->dispatch('tenant-theme-updated', primary: $this->primaryColor, secondary: $this->secondaryColor);
            $this->dispatch('notify', 'Theme updated!');
        }
    }

    public function updateTwoFactorSettings() {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (! app()->has('currentTenant')) {
            return;
        }

        $tenant = app('currentTenant');

        if (! $tenant->hasFeature('two-factor')) {
            $this->dispatch('notify', message: '2FA settings are only available on plans with 2FA.', type: 'error');
            return;
        }

        if (! Auth::user()?->hasRole('Team Supervisor')) {
            $this->dispatch('notify', message: 'Only workspace supervisors can change 2FA settings.', type: 'error');
            return;
        }

        $this->validate([
            'twoFactorScope' => 'required|in:all_members,supervisors_only',
            'twoFactorFrequency' => 'required|in:new_device,once_per_session,once_per_day,once_per_week',
            'twoFactorCodeTtlMinutes' => 'required|integer|min:5|max:30',
        ]);

        $actions = is_array($tenant->actions)
            ? $tenant->actions
            : ($tenant->actions ? json_decode((string) $tenant->actions, true) ?: [] : []);

        $actions['two_factor_settings'] = [
            'enabled' => (bool) $this->twoFactorEnabled,
            'scope' => $this->twoFactorScope,
            'frequency' => $this->twoFactorFrequency,
            'code_ttl_minutes' => (int) $this->twoFactorCodeTtlMinutes,
            'delivery' => 'email',
        ];

        $tenant->update(['actions' => $actions]);
        $this->updatingTwoFactor = false;

        $this->dispatch('notify', message: 'Two-factor authentication settings updated.', type: 'success');
    }

    public function deleteUser() {
        $this->validate(['current_password' => 'required|current_password']);
        $user = Auth::user();
        Auth::logout();
        $user->delete();
        return redirect()->to('/');
    }

    private function normalizeHexOptions(mixed $value, array $fallback): array
    {
        $options = is_array($value) ? $value : [];

        $normalized = collect($options)
            ->map(fn ($item) => strtoupper(trim((string) $item)))
            ->map(function (string $hex): ?string {
                if (!str_starts_with($hex, '#')) {
                    $hex = '#' . $hex;
                }

                return preg_match('/^#[0-9A-F]{6}$/', $hex) ? $hex : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalized !== [] ? $normalized : $fallback;
    }
}; ?>

<div class="bg-white min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center py-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Settings</h1>
                    <p class="text-gray-600 mb-0">Manage your account and preferences</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
        <div class="mx-auto max-w-7xl px-6 py-8 space-y-8">
            <!-- Profile Section -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-gray-900">Profile Information</h2>
                    <p class="text-gray-600 text-base mt-2">Update your personal details</p>
                </div>

                <div class="space-y-6"
                     x-data="{ uploadingAvatar: false, avatarUploadProgress: 0 }"
                     x-on:livewire-upload-start="uploadingAvatar = true"
                     x-on:livewire-upload-finish="uploadingAvatar = false; avatarUploadProgress = 0"
                     x-on:livewire-upload-error="uploadingAvatar = false"
                     x-on:livewire-upload-progress="avatarUploadProgress = $event.detail.progress">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Profile Photo</label>
                        <div class="flex items-center gap-4">
                            @php
                                $avatarSrc = null;
                                if (filled($avatarUrl ?? null)) {
                                    $avatarSrc = str_starts_with((string) $avatarUrl, 'http')
                                        ? $avatarUrl
                                        : Storage::url((string) $avatarUrl);
                                }
                            @endphp
                            @if($avatar)
                                <img src="{{ $avatar->temporaryUrl() }}" alt="Avatar preview" class="h-14 w-14 rounded-full object-cover border border-gray-200">
                            @elseif($avatarSrc)
                                <img src="{{ $avatarSrc }}" alt="Profile avatar" class="h-14 w-14 rounded-full object-cover border border-gray-200">
                            @else
                                <div class="h-14 w-14 rounded-full bg-gray-100 border border-gray-200 flex items-center justify-center text-gray-500 text-sm font-bold">
                                    {{ strtoupper(substr($name ?: (Auth::user()->name ?? 'U'), 0, 2)) }}
                                </div>
                            @endif

                            <div class="flex-1">
                                @if($this->canUploadProfileAvatar())
                                <input type="file"
                                       wire:model="avatar"
                                       wire:loading.attr="disabled"
                                       wire:target="avatar"
                                       accept="image/*"
                                       class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-nsync-green-600 file:px-3 file:py-2 file:font-semibold file:text-white hover:file:bg-nsync-green-700 disabled:opacity-60">
                                <p class="mt-1 text-xs text-gray-500">JPG, PNG, GIF, WEBP up to 2MB.</p>
                                @else
                                <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    Profile photo upload is locked on Free plan. Upgrade to Standard or Pro to enable attachments and avatar uploads.
                                </div>
                                @endif
                                <div x-show="uploadingAvatar" x-cloak class="mt-2">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-200">
                                        <div class="h-full bg-nsync-green-600 transition-all duration-200" :style="`width: ${avatarUploadProgress}%`"></div>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">Uploading photo... <span x-text="avatarUploadProgress"></span>%</p>
                                </div>
                                @error('avatar') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Full Name</label>
                        <input type="text" wire:model="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent transition text-base" placeholder="Your name" />
                        @error('name') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Email Address</label>
                        <input type="email" wire:model="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent transition text-base" placeholder="your@email.com" />
                        @error('email') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                    </div>

                    <button wire:click="updateProfile"
                            wire:loading.attr="disabled"
                            wire:target="avatar,updateProfile"
                            x-bind:disabled="uploadingAvatar"
                            class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                        <span wire:loading wire:target="updateProfile">Saving...</span>
                    </button>
                </div>
            </div>

            <!-- Theme Customization Section -->
            @if(app()->has('currentTenant'))
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6 flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Workspace Theme</h2>
                        <p class="text-gray-600 text-base mt-2">Customize the colors for your workspace</p>
                    </div>
                    <button wire:click="$toggle('updatingTheme')" class="text-nsync-green-700 hover:text-nsync-green-900 text-sm font-semibold transition">
                        {{ $updatingTheme ? 'Cancel' : 'Customize Theme' }}
                    </button>
                </div>

                @if($updatingTheme)
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Primary Color</label>
                            <div class="grid grid-cols-4 gap-3">
                                @foreach($primaryOptions as $color)
                                    <button type="button"
                                            wire:click="$set('primaryColor', '{{ $color }}')"
                                            class="h-12 rounded-lg border-2 transition {{ $primaryColor === $color ? 'border-nsync-green-600 scale-[1.02]' : 'border-gray-200' }}"
                                            style="background: {{ $color }};">
                                    </button>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Used for buttons and primary accents.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-3">Secondary Color</label>
                            <div class="grid grid-cols-5 gap-3">
                                @foreach($secondaryOptions as $color)
                                    <button type="button"
                                            wire:click="$set('secondaryColor', '{{ $color }}')"
                                            class="h-12 rounded-lg border-2 transition {{ $secondaryColor === $color ? 'border-nsync-green-600 scale-[1.02]' : 'border-gray-200' }}"
                                            style="background: {{ $color }};">
                                    </button>
                                @endforeach
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Used for backgrounds and secondary elements.</p>
                        </div>

                        <div class="bg-gray-50 p-6 rounded-lg border border-gray-200">
                            <p class="text-sm font-semibold text-gray-700 mb-4">Preview</p>
                            <div class="flex gap-3">
                                <button class="px-6 py-2 text-white font-medium rounded-lg transition" style="background-color: {{ $primaryColor }};">Preview Button</button>
                                <div class="flex-1 px-6 py-3 rounded-lg border" style="background-color: {{ $secondaryColor }}; border-color: {{ $primaryColor }}; color: {{ str_starts_with($secondaryColor, '#FFF') ? '#000' : '#FFF' }};"></div>
                            </div>
                        </div>

                        <button wire:click="updateTheme" class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition">Save Theme</button>
                    </div>
                @endif
            </div>
            @endif

            @if(app()->has('currentTenant') && app('currentTenant')->hasFeature('two-factor'))
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6 flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Two-Factor Authentication</h2>
                        <p class="text-gray-600 text-base mt-2">Control how and when 2FA is required for your workspace.</p>
                    </div>
                    <button wire:click="$toggle('updatingTwoFactor')" class="text-nsync-green-700 hover:text-nsync-green-900 text-sm font-semibold transition">
                        {{ $updatingTwoFactor ? 'Cancel' : 'Configure 2FA' }}
                    </button>
                </div>

                <div class="rounded-lg border border-emerald-100 bg-emerald-50 px-4 py-3 text-xs text-emerald-800">
                    2FA is available on your plan, but only enforced when you enable it here.
                </div>

                @if($updatingTwoFactor)
                    <div class="mt-6 space-y-6">
                        <label class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Enable 2FA Enforcement</p>
                                <p class="text-xs text-gray-500">Require verification code before accessing the app.</p>
                            </div>
                            <input type="checkbox" wire:model="twoFactorEnabled" class="h-5 w-5 rounded text-nsync-green-600">
                        </label>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Apply 2FA To</label>
                            <select wire:model="twoFactorScope" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-sm">
                                <option value="all_members">All members</option>
                                <option value="supervisors_only">Supervisors only</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Challenge Frequency</label>
                            <select wire:model="twoFactorFrequency" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-sm">
                                <option value="new_device">Only on new device/browser</option>
                                <option value="once_per_session">Once per login session</option>
                                <option value="once_per_day">Once every day</option>
                                <option value="once_per_week">Once every week</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Code Expiry (Minutes)</label>
                            <input type="number" min="5" max="30" wire:model="twoFactorCodeTtlMinutes" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-sm" placeholder="10">
                            <p class="text-xs text-gray-500 mt-1">Allowed range: 5 to 30 minutes.</p>
                        </div>

                        <button wire:click="updateTwoFactorSettings" class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition">Save 2FA Settings</button>
                    </div>
                @endif
            </div>
            @endif

            <!-- Password Section -->
            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <div class="mb-6 flex justify-between items-start">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">Password</h2>
                        <p class="text-gray-600 text-base mt-2">Change your password to keep your account secure</p>
                    </div>
                    <button wire:click="$toggle('updatingPassword')" class="text-nsync-green-700 hover:text-nsync-green-900 text-sm font-semibold transition">
                        {{ $updatingPassword ? 'Cancel' : 'Change Password' }}
                    </button>
                </div>

                @if($updatingPassword)
                    <div class="space-y-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                            <input type="password" wire:model="current_password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent transition text-base" placeholder="Enter current password" />
                            @error('current_password') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" wire:model="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent transition text-base" placeholder="Enter new password" />
                            @error('password') <span class="text-red-600 text-xs mt-1">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" wire:model="password_confirmation" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent transition text-base" placeholder="Confirm new password" />
                        </div>

                        <button wire:click="updatePassword" class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition">Update Password</button>
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
