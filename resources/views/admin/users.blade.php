@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold bg-gradient-to-r from-gray-900 to-gray-700 bg-clip-text text-transparent dark:from-gray-100 dark:to-gray-300">Users</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Manage platform users across all tenants</p>
        </div>
        <button class="px-6 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-bold rounded-2xl hover:shadow-lg hover:-translate-y-0.5 transition-all shadow-md">+ Invite User</button>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-3xl border border-gray-200/50 dark:border-gray-700/50 shadow-xl overflow-hidden">
        <div class="p-6 border-b border-gray-200/50 dark:border-gray-700/50">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <input type="text" placeholder="Search users..." class="w-64 px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-sm">
                    <button class="p-2.5 bg-gray-100 dark:bg-gray-700 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </button>
                </div>
                <select class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
                    <option>All Tenants</option>
                    <option>Approved</option>
                    <option>Pending</option>
                </select>
                <select class="px-4 py-2 border border-gray-200 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-700 text-sm focus:ring-2 focus:ring-blue-500">
                    <option>All Roles</option>
                    <option>Platform Admin</option>
                    <option>Team Supervisor</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700/50">
                    <tr>
class="px-6 py-4 text-left text-sm font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
class="px-6 py-4 text-left text-sm font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tenant</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-4 text-right text-xs font-bold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($users ?? [] as $user)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center text-white font-semibold text-sm shadow-md">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900 dark:text-white text-sm">{{ $user->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">@verified</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->email }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-emerald-100 dark:bg-emerald-900/30 text-emerald-800 dark:text-emerald-200 text-xs font-semibold rounded-full">
                                {{ $user->tenant->name ?? 'Platform Admin' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-200 text-xs font-semibold rounded-full">
                                {{ $user->roles->pluck('name')->implode(', ') }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="px-2 py-1 {{ $user->email_verified_at ? 'bg-green-100 dark:bg-green-900/30 text-green-800 dark:text-green-200' : 'bg-amber-100 dark:bg-amber-900/30 text-amber-800 dark:text-amber-200' }} text-xs font-semibold rounded-full">
                                {{ $user->email_verified_at ? 'Verified' : 'Pending' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-2">
                            <button class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.5h3m1 0v1a2 2 0 01-2 2H9a2 2 0 01-2-2v-1"></path>
                                </svg>
                            </button>
                            <button class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="text-gray-500 dark:text-gray-400">
                                <svg class="w-16 h-16 mx-auto mb-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <h3 class="text-lg font-semibold mb-2">No users found</h3>
                                <p class="mb-4">Get started by inviting your first user.</p>
                                <button class="px-6 py-2 bg-blue-600 text-white text-sm font-bold rounded-xl hover:bg-blue-700 transition-all shadow-lg">Invite User</button>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="p-6 bg-gray-50 dark:bg-gray-700/50 rounded-xl border border-gray-200/50 dark:border-gray-700/50">
            <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">User Statistics</h3>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Total Users</span>
                    <span class="text-2xl font-bold text-blue-600 dark:text-blue-400">1,247</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Active Today</span>
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">89</span>
                </div>
                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                    <span class="text-sm font-semibold text-gray-900 dark:text-white">Verified</span>
                    <span class="text-2xl font-bold text-green-600 dark:text-green-400">1,156</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
