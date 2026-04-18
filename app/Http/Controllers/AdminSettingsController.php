<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    public function edit()
    {
        $plans = config('plans');
        $settings = AppSetting::data();

        return view('admin.settings', [
            'plans' => $plans,
            'settings' => $settings,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $plans = array_keys(config('plans'));

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'current_password' => 'nullable|string',
            'new_password' => 'nullable|string|min:8|confirmed',
            'default_plan' => 'required|in:' . implode(',', $plans),
            'notify_new_tenant' => 'sometimes|boolean',
            'maintenance_enabled' => 'sometimes|boolean',
            'maintenance_message' => 'nullable|string|max:255',
            'support_email' => 'nullable|email',
        ]);

        // Update admin profile
        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($validated['new_password'] ?? false) {
            if (!Hash::check($validated['current_password'] ?? '', $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Current password is incorrect.',
                ]);
            }
            $user->password = Hash::make($validated['new_password']);
        }
        $user->save();

        $payload = [
            'default_plan' => $validated['default_plan'],
            'notify_new_tenant' => $request->boolean('notify_new_tenant'),
            'maintenance_enabled' => $request->boolean('maintenance_enabled'),
            'maintenance_message' => $validated['maintenance_message'] ?? null,
            'support_email' => $validated['support_email'] ?? null,
        ];

        AppSetting::updateSettings($payload);

        return back()->with('success', 'Settings updated.');
    }
}
