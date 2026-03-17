<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Exception;
use Illuminate\Support\Facades\Log;


class GoogleAuthController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Exception $e) {
            Log::error('Google OAuth Callback Error: ' . $e->getMessage());
            return redirect('/login')->with('error', 'Google login failed. Please check console logs.'); 
        }

        $user = User::updateOrCreate(
            ['email' => $googleUser->getEmail()],
            [
                'name' => $googleUser->getName(),
                'avatar' => $googleUser->getAvatar(),
                'password' => bcrypt('google-password'),
            ]
        );

        if (!$user->tenant_id) {
            $tenant = \App\Models\Tenant::create([
                'name' => $user->name . ' Workspace',
                'domain' => str($user->email)->before('@')->slug(),
'database' => str($user->email)->before('@')->slug(),
                'plan' => 'free',
                'status' => 'approved',
            ]);
            $user->update(['tenant_id' => $tenant->id]);
            $user->tenants()->attach($tenant->id, ['role' => 'owner']);

        }

        $user->assignRole('Team Supervisor');

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}

