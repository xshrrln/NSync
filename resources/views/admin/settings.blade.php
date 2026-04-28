@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-widest text-green-700 font-semibold">Settings</p>
            <h1 class="text-2xl font-black text-gray-900 mt-1">Platform Settings</h1>
            <p class="text-sm text-gray-600 mt-2">Control defaults, notifications, and system banners for the central app.</p>
        </div>
        <span class="px-3 py-1 rounded-full bg-green-50 text-green-700 text-xs font-semibold">Central</span>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <div class="xl:col-span-2 space-y-6">
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Admin Profile</p>
                            <p class="text-sm text-gray-600">Who you appear as across the platform.</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Name</label>
                        <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" required>
                        @error('name') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Email</label>
                        <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" required>
                        @error('email') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Password</p>
                        <p class="text-sm text-gray-600">Change your admin password.</p>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Current password</label>
                        <x-password-input name="current_password" autocomplete="current-password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="Current password" />
                        @error('current_password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">New password</label>
                            <x-password-input name="new_password" autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="At least 8 characters" />
                            @error('new_password') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-700">Confirm password</label>
                            <x-password-input name="new_password_confirmation" autocomplete="new-password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="Repeat new password" />
                        </div>
                    </div>

                    <p class="text-xs text-gray-500">Leave password fields blank to keep your current password.</p>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Tenant Defaults</p>
                            <p class="text-sm text-gray-600">Apply to new tenants created by admins.</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Default plan</label>
                        <select name="default_plan" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500">
                            @foreach($plans as $key => $plan)
                                <option value="{{ $key }}" @selected(($settings['default_plan'] ?? 'free') === $key)>{{ ucfirst($key) }} ({{ $plan['price'] }})</option>
                            @endforeach
                        </select>
                        @error('default_plan') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="notify_new_tenant" value="1" class="rounded text-green-600 border-gray-300 focus:ring-green-500" {{ ($settings['notify_new_tenant'] ?? false) ? 'checked' : '' }}>
                        Email me when a new tenant is created
                    </label>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Primary Theme Colors (HEX)</label>
                        <textarea
                            name="theme_primary_options"
                            rows="4"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-green-500"
                            placeholder="#16A34A&#10;#34D399&#10;#60A5FA"
                        >{{ old('theme_primary_options', implode(PHP_EOL, $settings['theme_primary_options'] ?? [])) }}</textarea>
                        <p class="text-xs text-gray-500">Enter one HEX color per line (or comma/space separated). These appear in tenant Settings.</p>
                        @error('theme_primary_options') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Secondary Theme Colors (HEX)</label>
                        <textarea
                            name="theme_secondary_options"
                            rows="4"
                            class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-green-500"
                            placeholder="#FFFFFF&#10;#F8FAFC&#10;#ECFEFF"
                        >{{ old('theme_secondary_options', implode(PHP_EOL, $settings['theme_secondary_options'] ?? [])) }}</textarea>
                        <p class="text-xs text-gray-500">Enter one HEX color per line (or comma/space separated). Keep light tones for readability.</p>
                        @error('theme_secondary_options') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6 space-y-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest text-gray-500 font-semibold">Maintenance</p>
                        <p class="text-sm text-gray-600">Show a banner across admin pages.</p>
                    </div>

                    <label class="flex items-center gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="maintenance_enabled" value="1" class="rounded text-green-600 border-gray-300 focus:ring-green-500" {{ ($settings['maintenance_enabled'] ?? false) ? 'checked' : '' }}>
                        Show maintenance / status banner
                    </label>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Banner message</label>
                        <input type="text" name="maintenance_message" value="{{ old('maintenance_message', $settings['maintenance_message'] ?? '') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="e.g. Deploying updates at 9 PM UTC">
                        @error('maintenance_message') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-gray-700">Support email</label>
                        <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-green-500" placeholder="support@example.com">
                        @error('support_email') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-green-600 text-white font-bold rounded-xl hover:bg-green-700 transition shadow-md">Save settings</button>
        </div>
    </form>
</div>
@endsection
