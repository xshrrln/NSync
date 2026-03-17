<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Volt;

// 1. Pivot Logic: Force Dashboard if logged in, otherwise force Login
Route::get('/', function () {
    return Auth::check() 
        ? redirect()->route('dashboard') 
        : redirect()->route('login');
});

// 2. NSync Workspace: Only accessible to authenticated & verified users
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    
    Volt::route('/dashboard', 'dashboard')->name('dashboard');
    Volt::route('/tenant-request', 'tenant-request')->name('tenant.request');
    
    Volt::route('/boards', 'board-list')->name('boards.index');
    Volt::route('/boards/{slug}', 'kanban-board')->name('boards.show');

    // Profile Management
    Volt::route('/settings', 'settings')->name('settings');
    
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes
Route::middleware(['auth', 'verified', 'role:Platform Administrator', 'tenant'])->prefix('admin')->name('admin.')->group(function () {
    Volt::route('/tenants', 'admin.tenant-approval')->name('tenants.index');
    Route::patch('/tenants/{tenant}/approve', [TenantController::class, 'approve'])->name('tenants.approve');
});

Route::get('/auth/google', [App\Http\Controllers\GoogleAuthController::class, 'redirectToGoogle'])->name('google.redirect')->withoutMiddleware([\App\Http\Middleware\IdentifyTenant::class]);
Route::get('/auth/google/callback', [App\Http\Controllers\GoogleAuthController::class, 'handleGoogleCallback'])->name('google.callback')->withoutMiddleware([\App\Http\Middleware\IdentifyTenant::class, \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class]);

require __DIR__.'/auth.php';
