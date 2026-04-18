<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminSettingsController;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

// 1. Pivot Logic: Fixed redirects to ensure users go to the correct home
Route::get('/', function () {
    if (Auth::check()) {
        $user = Auth::user();
        
        // Admin users go to the Platform Admin Dashboard
        if ($user->hasRole('Platform Administrator')) {
            return redirect(route('admin.dashboard'));
        }
        
        // Regular users go to their Tenant Workspace Dashboard
        return redirect(route('dashboard'));
    }
    // Force login when explicitly requested via query flag (for returning users/bookmarks)
    if (request()->boolean('login')) {
        return redirect()->route('login');
    }
    // Not authenticated: show public landing page
    return view('landing');
})->name('landing');

// Pending approval page for users without tenant
Route::view('/pending-approval', 'auth.pending-approval')->name('pending-approval');

// 2. Admin routes: register central dashboard before generic workspace routes so
// nsync.localhost/dashboard resolves to the admin page instead of the tenant Volt page.
Route::domain('nsync.localhost')->middleware(['auth', 'verified', 'role:Platform Administrator'])->get('/dashboard', function () {
    $tenantsCount = \App\Models\Tenant::count();
    $pendingCount = \App\Models\Tenant::where('status', 'pending')->count();
    $activeCount = \App\Models\Tenant::where('status', 'active')->count();
    $suspendedCount = \App\Models\Tenant::where('status', 'disabled')->count();
    
    return view('admin.dashboard', compact('tenantsCount', 'pendingCount', 'activeCount', 'suspendedCount'));
})->name('admin.dashboard');

Route::domain('nsync.localhost')->middleware(['auth', 'verified', 'role:Platform Administrator'])->prefix('admin')->name('admin.')->group(function () {
    
    Route::get('/dashboard', function () {
        $tenantsCount = \App\Models\Tenant::count();
        $pendingCount = \App\Models\Tenant::where('status', 'pending')->count();
        $activeCount = \App\Models\Tenant::where('status', 'active')->count();
        $suspendedCount = \App\Models\Tenant::where('status', 'disabled')->count();
        
        // FIXED: Using 'admin.dashboard' to target resources/views/admin/dashboard.blade.php
        return view('admin.dashboard', compact('tenantsCount', 'pendingCount', 'activeCount', 'suspendedCount'));
    })->name('dashboard');
    
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::patch('/tenants/{tenant}/approve', [TenantController::class, 'approve'])->name('tenants.approve');
    Route::patch('/tenants/{tenant}/reject', [TenantController::class, 'reject'])->name('tenants.reject');
    Route::patch('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
    Route::patch('/tenants/{tenant}/resume', [TenantController::class, 'resume'])->name('tenants.resume');
    Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::patch('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{tenant}/upgrade-plan', [TenantController::class, 'upgradePlan'])->name('tenants.upgrade-plan');

    // Admin utilities
    Route::view('/plans-manager', 'admin.plans-manager')->name('plans.manager');
    Route::view('/billing', 'admin.billing')->name('billing');
    Route::view('/patches', 'admin.patches')->name('patches');
    Route::view('/archive', 'admin.archive')->name('archive');
    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings');
    Route::post('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');
});

// 3. NSync Workspace: Tenant-specific routes
Route::middleware(['auth', 'verified', 'tenant', 'approved'])->group(function () {
    
    Volt::route('/dashboard', 'dashboard')->name('dashboard');
    Volt::route('/tenant-request', 'tenant-request')->name('tenant.request');
    
    Volt::route('/boards', 'board-list')->name('boards.index');
    Volt::route('/boards/{slug}', 'kanban-board')->name('boards.show');

    // Profile Management
    Volt::route('/settings', 'settings')->name('settings');
    Volt::route('/team-members', 'team-members')->name('team-members');
    Volt::route('/chat', 'chat-window')->name('chat');
    Volt::route('/update-center', 'update-center')->name('update-center');
    Volt::route('/billing', 'billing')->name('billing');
    Volt::route('/reports', 'reports')->name('reports');
    Volt::route('/team/invite', 'team-members')->name('team.invite');
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'show'])->name('two-factor.challenge.show');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->name('two-factor.verify');
    Route::post('/two-factor-challenge/resend', [TwoFactorChallengeController::class, 'resend'])->name('two-factor.resend');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Guest board view (read-only) for board sharing links.
Route::middleware(['tenant'])->group(function () {
    Volt::route('/guest/boards/{slug}/{token}', 'guest-board')->name('guest.board.view');
});

// Public invite acceptance (no login required yet).
Volt::route('/team/invite/accept/{token}', 'team-invite-accept')->name('team.invite.accept');

Route::get('/auth/google', [App\Http\Controllers\GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect')->withoutMiddleware([\App\Http\Middleware\IdentifyTenant::class]);
Route::get('/auth/google/callback', [App\Http\Controllers\GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback')->withoutMiddleware([\App\Http\Middleware\IdentifyTenant::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

require __DIR__.'/auth.php';
