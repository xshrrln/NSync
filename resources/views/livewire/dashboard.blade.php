<?php
use Livewire\Volt\Component;
use App\Models\ActivityLog;
use App\Models\Board;
use App\Models\Stage;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

new class extends Component {
    public $newBoardName = '';

    private function ensureSubscriptionAccess(string $title = 'Subscription Required'): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $tenant->requiresSubscriptionRenewal()) {
            return true;
        }

        $this->dispatch('subscription-expired', title: $title, message: $tenant->subscriptionLockMessage());

        return false;
    }

    public function with() {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $canViewAnalytics = $tenant ? $tenant->hasFeature('basic-analytics') : false;
        $boards = $tenant ? Board::withCount('tasks')->latest()->get() : collect();
        $capstoneCounts = $this->capstoneTaskCountsByBoard($boards);
        $boards = $boards->map(function (Board $board) use ($capstoneCounts) {
            $capstone = (int) ($capstoneCounts[$board->id] ?? 0);
            $kanban = (int) ($board->tasks_count ?? 0);
            $board->setAttribute('capstone_tasks_count', $capstone);
            $board->setAttribute('tasks_total_count', $kanban + $capstone);
            return $board;
        });

        $totalTasks = (int) $boards->sum(fn (Board $board) => (int) ($board->tasks_total_count ?? 0));
        $stageBreakdown = ($tenant && $canViewAnalytics) ? Stage::withCount('tasks')->get() : collect();
        $taskTrend = ($tenant && $canViewAnalytics) ? $this->buildWorkloadTrend() : collect();
        $memberWorkload = ($tenant && $canViewAnalytics) ? $this->buildMemberWorkload() : collect();
        return [
            'boards' => $boards,
            'totalTasks' => $tenant ? $totalTasks : 0,
            'totalStages' => $tenant ? Stage::count() : 0,
            'tenant' => $tenant,
            'stageBreakdown' => $stageBreakdown,
            'taskTrend' => $taskTrend,
            'memberWorkload' => $memberWorkload,
            'canViewAnalytics' => $canViewAnalytics,
            'canCreateBoards' => Gate::allows('create', Board::class),
        ];
    }

    private function buildWorkloadTrend()
    {
        $boardCreatesByDate = collect();
        $boardEditsByDate = collect();
        $taskCreatesByDate = collect();
        $taskEditsByDate = collect();
        $boardMoveActionsByDate = collect();
        try {
            if (Schema::connection('tenant')->hasTable('boards')) {
                $boardCreatesByDate = Board::query()
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');

                $boardEditsByDate = Board::query()
                    ->whereColumn('updated_at', '!=', 'created_at')
                    ->selectRaw('DATE(updated_at) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');
            }

            if (Schema::connection('tenant')->hasTable('tasks')) {
                $taskCreatesByDate = Task::query()
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');

                $taskEditsByDate = Task::query()
                    ->whereColumn('updated_at', '!=', 'created_at')
                    ->selectRaw('DATE(updated_at) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');
            }

            if (Schema::connection('tenant')->hasTable('activity_logs')) {
                $boardMoveActionsByDate = ActivityLog::query()
                    ->selectRaw('DATE(created_at) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');
            }
        } catch (\Throwable $e) {
            Log::warning('Workload trend fallback triggered.', [
                'error' => $e->getMessage(),
            ]);
        }

        $totals = [];

        foreach ($boardCreatesByDate as $date => $count) {
            $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
        }
        foreach ($boardEditsByDate as $date => $count) {
            $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
        }
        foreach ($taskCreatesByDate as $date => $count) {
            $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
        }
        foreach ($taskEditsByDate as $date => $count) {
            $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
        }
        foreach ($boardMoveActionsByDate as $date => $count) {
            $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
        }

        if (empty($totals)) {
            try {
                $boardUpdatesByDate = Board::query()
                    ->selectRaw('DATE(COALESCE(updated_at, created_at)) as date, COUNT(*) as total')
                    ->groupBy('date')
                    ->pluck('total', 'date');

                foreach ($boardUpdatesByDate as $date => $count) {
                    $totals[$date] = (int) ($totals[$date] ?? 0) + (int) $count;
                }
            } catch (\Throwable $e) {
                Log::warning('Workload trend board fallback failed.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return collect($totals)
            ->map(fn (int $total, string $date) => ['date' => $date, 'total' => $total])
            ->sortByDesc('date')
            ->take(7)
            ->sortBy('date')
            ->values();
    }

    private function buildMemberWorkload()
    {
        if (! Schema::connection('tenant')->hasTable('users') || ! Schema::connection('tenant')->hasTable('tasks')) {
            return collect();
        }

        try {
            $doneStageIds = Stage::query()
                ->whereIn(DB::raw('LOWER(name)'), ['done', 'completed', 'complete', 'resolved', 'published'])
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $query = DB::connection('tenant')
                ->table('users as u')
                ->leftJoin('tasks as t', 't.user_id', '=', 'u.id')
                ->selectRaw('u.id, u.name, COUNT(t.id) as total_tasks, COUNT(DISTINCT t.board_id) as boards_touched');

            if (! empty($doneStageIds)) {
                $doneList = implode(',', array_map('intval', $doneStageIds));
                $query->selectRaw("SUM(CASE WHEN t.stage_id IN ({$doneList}) THEN 1 ELSE 0 END) as completed_tasks");
            } else {
                $query->selectRaw('0 as completed_tasks');
            }

            return $query
                ->groupBy('u.id', 'u.name')
                ->orderByDesc('total_tasks')
                ->limit(8)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Unable to load member workload analytics.', [
                'error' => $e->getMessage(),
            ]);
            return collect();
        }
    }

    private function capstoneTaskCountsByBoard($boards): array
    {
        return $boards->mapWithKeys(function (Board $board) {
            $members = is_array($board->members) ? $board->members : [];
            $capstone = $members['capstone_tasks'] ?? [];
            $count = is_array($capstone)
                ? count(array_filter($capstone, fn ($row) => is_array($row) && filled((string) ($row['title'] ?? ''))))
                : 0;

            return [$board->id => $count];
        })->all();
    }

    public function createBoard() {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!Gate::allows('create', Board::class)) {
            $this->addError('newBoardName', 'You are not allowed to create boards on this plan.');
            return;
        }

        $this->validate(['newBoardName' => 'required|min:3|max:50']);

        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        if (! $tenant) {
            $this->addError('newBoardName', 'No workspace is currently selected.');
            return;
        }

        $boardName = trim($this->newBoardName) . ' ' . now()->format('M d');

        $board = Board::forceCreate([
            'tenant_id' => $tenant->id,
            'user_id' => Auth::id(),
            'name' => $boardName,
            'slug' => str($boardName)->slug() . '-' . rand(100, 999),
        ]);

        $board->stages()->createMany([
            ['tenant_id' => $tenant->id, 'name' => 'Design', 'position' => 1],
            ['tenant_id' => $tenant->id, 'name' => 'To Do', 'position' => 2],
            ['tenant_id' => $tenant->id, 'name' => 'In Progress', 'position' => 3],
            ['tenant_id' => $tenant->id, 'name' => 'Testing', 'position' => 4],
            ['tenant_id' => $tenant->id, 'name' => 'Done', 'position' => 5],
        ]);

        $this->newBoardName = '';
        $this->dispatch('notify', message: 'Board created successfully!', type: 'success');
    }
}; ?>

<div class="pb-5 bg-white min-h-screen">
    <!-- Header -->
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center py-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Dashboard</h1>
                    <p class="text-gray-600 mb-0">Welcome back, {{ Auth::user()->name }}!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-6 py-8">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- Total Boards -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Boards</h6>
                        <div class="bg-nsync-green-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-nsync-green-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $boards->count() }}</h2>
                    <p class="text-gray-600 text-sm mb-0 flex items-center gap-1">
                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                        Active projects
                    </p>
                </div>
            </div>

            <!-- Total Tasks -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Tasks</h6>
                        <div class="bg-nsync-green-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-nsync-green-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ number_format($totalTasks) }}</h2>
                    <p class="text-gray-600 text-base mb-0">Total tasks across all boards</p>
                </div>
            </div>

            <!-- Total Stages -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">Total Stages</h6>
                        <div class="bg-nsync-green-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-nsync-green-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ number_format($totalStages) }}</h2>
                    <p class="text-gray-600 text-sm mb-0">Workflow stages configured</p>
                </div>
            </div>

            <!-- System Status -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full">
                <div class="p-6 flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h6 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-0">System Status</h6>
                        <div class="bg-nsync-green-100 rounded-full flex items-center justify-center w-10 h-10">
                            <svg class="text-nsync-green-600 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ strtoupper($tenant->plan ?? 'free') }} Plan</h2>
                    <p class="text-gray-600 text-sm mb-0">Your subscription tier</p>
                </div>
            </div>
        </div>

        @if($canViewAnalytics)
            <!-- Analytics -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h5 class="text-lg font-bold text-gray-900 mb-1">Workload Trend</h5>
                            <p class="text-base text-gray-600 mb-0">Real activity across the 7 most recent active dates</p>
                        </div>
                        <span class="text-xs font-semibold text-nsync-green-700 bg-nsync-green-50 px-3 py-1 rounded-full">Live</span>
                    </div>
                    <canvas id="taskTrendChart" class="w-full h-64"></canvas>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h5 class="text-lg font-bold text-gray-900 mb-1">Task Distribution</h5>
                            <p class="text-base text-gray-600 mb-0">How work is spread across boards</p>
                        </div>
                        <span class="text-xs font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded-full">{{ $boards->count() }} boards</span>
                    </div>
                    <canvas id="boardBreakdownChart" class="w-full h-64"></canvas>
                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @forelse($stageBreakdown->take(4) as $stage)
                            <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-nsync-green-50 text-nsync-green-800 border border-nsync-green-100">
                                <span class="font-semibold truncate">{{ $stage->name }}</span>
                                <span class="text-sm font-bold">{{ $stage->tasks_count ?? 0 }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">No stages yet. Create a board to start tracking tasks.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="mb-8 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h5 class="text-lg font-bold text-gray-900 mb-1">Member Workload</h5>
                        <p class="text-base text-gray-600 mb-0">Tasks each member handled across all boards</p>
                    </div>
                    <span class="text-xs font-semibold text-gray-700 bg-gray-100 px-3 py-1 rounded-full">{{ $memberWorkload->count() }} members</span>
                </div>
                <div class="mx-auto flex h-48 w-full max-w-lg items-center justify-center">
                    <canvas id="memberWorkloadChart" class="block h-full w-full"></canvas>
                </div>
                @php
                    $totalMemberTasksForList = max(1, (int) $memberWorkload->sum(fn ($member) => (int) ($member->total_tasks ?? 0)));
                @endphp
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @forelse($memberWorkload->take(9) as $member)
                        @php
                            $memberTotalTasks = (int) ($member->total_tasks ?? 0);
                            $memberCompletedTasks = (int) ($member->completed_tasks ?? 0);
                            $memberWorkloadPercent = ($memberTotalTasks / $totalMemberTasksForList) * 100;
                        @endphp
                        <div class="flex items-center justify-between px-3 py-2 rounded-lg bg-nsync-green-50 text-nsync-green-800 border border-nsync-green-100">
                            <span class="font-semibold truncate">{{ ($member->name ?? 'Unknown') . ' - ' . number_format($memberWorkloadPercent, 1) . '%' }}</span>
                            <span class="text-xs font-bold text-right">
                                {{ number_format($memberTotalTasks) }} total
                                • {{ number_format($memberCompletedTasks) }} done
                            </span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No member workload data available yet.</p>
                    @endforelse
                </div>
            </div>
        @else
            <div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-6 py-4 text-sm text-amber-900">
                Basic analytics is not included in your current plan.
            </div>
        @endif

        <!-- Quick Actions -->
        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="p-6">
                    <h5 class="text-lg font-bold text-gray-900 mb-4">Quick Actions</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        @if($canCreateBoards)
                            <form wire:submit="createBoard" class="flex gap-3">
                                <input type="text" wire:model="newBoardName" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-base" placeholder="Enter board name..." required>
                                <button type="submit" class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition shadow-sm flex items-center gap-2">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                    </svg>
                                    Create Board
                                </button>
                            </form>
                        @else
                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                Board creation is not available on your current plan.
                            </div>
                        @endif
                        <div class="flex items-center">
                            <a href="{{ route('boards.index') }}" class="px-6 py-2 bg-white border border-gray-300 text-gray-900 font-medium rounded-lg hover:border-nsync-green-300 hover:text-nsync-green-700 transition shadow-sm flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Manage Boards
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Boards Section -->
        <div class="mb-8">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Your Boards</h2>
                    <p class="text-gray-600 mb-0">Quick access to your recent projects</p>
                </div>
                <a href="{{ route('boards.index') }}" class="px-4 py-2 bg-nsync-green-50 text-nsync-green-700 font-medium rounded-lg hover:bg-nsync-green-100 transition flex items-center gap-2">
                    View All
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </div>

        @if($boards->count() > 0)
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                @forelse($boards->take(8) as $board)
                    <a href="{{ route('boards.show', $board->slug) }}" class="text-decoration-none">
                        <div class="relative h-32 bg-gradient-to-br from-nsync-green-500 to-nsync-green-600 rounded-lg shadow-sm hover:shadow-lg transition-all cursor-pointer overflow-hidden">
                            <div class="absolute inset-0 flex flex-col justify-between p-6 text-white">
                                <h6 class="font-bold text-lg leading-tight truncate">{{ $board->name }}</h6>
                                <small class="opacity-90">Open board</small>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-full">
                        <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg text-center py-12">
                            <div class="mb-4">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-nsync-green-100 rounded-full">
                                    <svg class="text-nsync-green-600 w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                                    </svg>
                                </div>
                            </div>
                            <h3 class="text-lg font-bold text-gray-900 mb-3">No boards yet</h3>
                            <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                            <a href="{{ route('boards.index') }}" class="inline-flex items-center px-6 py-3 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition shadow-sm">
                                Create Your First Board
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg text-center py-12">
                <div class="mb-4">
                    <div class="inline-flex items-center justify-center w-20 h-20 bg-nsync-green-100 rounded-full">
                        <svg class="text-nsync-green-600 w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                        </svg>
                    </div>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-3">No boards yet</h3>
                <p class="text-gray-600 mb-6 max-w-sm mx-auto">Create your first board to start organizing your projects and managing tasks</p>
                <a href="{{ route('boards.index') }}" class="inline-flex items-center px-6 py-3 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition shadow-sm">
                    Create Your First Board
                </a>
            </div>
        @endif
    </div>
</div>

@php
    $trendLabels = $taskTrend->pluck('date')->map(fn($date) => Carbon::parse($date)->format('M d'))->values();
    $trendData = $taskTrend->pluck('total')->values();
    $boardLabels = $boards->pluck('name')->values();
    $boardData = $boards->pluck('tasks_total_count')->map(fn ($count) => (int) $count)->values();
    $memberWorkloadLabels = $memberWorkload->pluck('name')->values();
    $memberWorkloadData = $memberWorkload->pluck('total_tasks')->map(fn ($count) => (int) $count)->values();
@endphp

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        window.__nsyncCharts = window.__nsyncCharts || {};

        const hexToRgb = (hex) => {
            const value = String(hex || '').trim().replace('#', '');
            const normalized = value.length === 3
                ? value.split('').map((c) => c + c).join('')
                : value;

            if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
                return { r: 22, g: 163, b: 74 };
            }

            return {
                r: parseInt(normalized.slice(0, 2), 16),
                g: parseInt(normalized.slice(2, 4), 16),
                b: parseInt(normalized.slice(4, 6), 16),
            };
        };

        const rgba = (rgb, alpha = 1) => `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
        const mixWithWhite = (rgb, ratio = 0.75) => ({
            r: Math.round(rgb.r + (255 - rgb.r) * ratio),
            g: Math.round(rgb.g + (255 - rgb.g) * ratio),
            b: Math.round(rgb.b + (255 - rgb.b) * ratio),
        });

        const getTenantPrimary = () => {
            const raw = getComputedStyle(document.body).getPropertyValue('--tenant-primary').trim();
            return raw || '#16A34A';
        };

        const buildThemePalette = () => {
            const primaryHex = getTenantPrimary();
            const primaryRgb = hexToRgb(primaryHex);
            const softRgb = mixWithWhite(primaryRgb, 0.78);

            return {
                primaryHex,
                primaryRgb,
                softRgb,
                lineBorder: primaryHex,
                lineFill: rgba(primaryRgb, 0.16),
                barStart: rgba(primaryRgb, 0.95),
                barEnd: rgba(primaryRgb, 0.55),
                pieStart: primaryRgb,
                pieEnd: softRgb,
            };
        };

        const buildPieGradientColors = (labels, startRgb, endRgb) => {
            return labels.map((_, index) => {
                const steps = Math.max(1, labels.length - 1);
                const t = labels.length <= 2 ? index : (index / steps);
                const r = Math.round(startRgb.r + (endRgb.r - startRgb.r) * t);
                const g = Math.round(startRgb.g + (endRgb.g - startRgb.g) * t);
                const b = Math.round(startRgb.b + (endRgb.b - startRgb.b) * t);
                return `rgb(${r}, ${g}, ${b})`;
            });
        };

        const applyThemeToDashboardCharts = () => {
            const palette = buildThemePalette();

            if (window.__nsyncCharts.trend) {
                const trendDataset = window.__nsyncCharts.trend.data.datasets?.[0];
                if (trendDataset) {
                    trendDataset.borderColor = palette.lineBorder;
                    trendDataset.backgroundColor = palette.lineFill;
                    trendDataset.pointBackgroundColor = palette.lineBorder;
                    window.__nsyncCharts.trend.update();
                }
            }

            if (window.__nsyncCharts.boardBreakdown) {
                const boardChart = window.__nsyncCharts.boardBreakdown;
                const gradient = boardChart.ctx.createLinearGradient(0, 0, 0, 260);
                gradient.addColorStop(0, palette.barStart);
                gradient.addColorStop(1, palette.barEnd);

                const boardDataset = boardChart.data.datasets?.[0];
                if (boardDataset) {
                    boardDataset.backgroundColor = gradient;
                    boardDataset.borderColor = palette.lineBorder;
                    boardChart.update();
                }
            }

            if (window.__nsyncCharts.memberWorkload) {
                const memberChart = window.__nsyncCharts.memberWorkload;
                const colors = buildPieGradientColors(
                    memberChart.data.labels || [],
                    palette.pieStart,
                    palette.pieEnd
                );
                const memberDataset = memberChart.data.datasets?.[0];
                if (memberDataset) {
                    memberDataset.backgroundColor = colors;
                    memberChart.update();
                }
            }
        };

        const themePalette = buildThemePalette();
        const trendCtx = document.getElementById('taskTrendChart');
        if (trendCtx) {
            if (window.__nsyncCharts.trend) {
                window.__nsyncCharts.trend.destroy();
            }

            window.__nsyncCharts.trend = new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: @json($trendLabels),
                    datasets: [{
                        label: 'Activity Events',
                        data: @json($trendData),
                        borderColor: themePalette.lineBorder,
                        backgroundColor: themePalette.lineFill,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 4,
                        pointBackgroundColor: themePalette.lineBorder
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        const boardCtx = document.getElementById('boardBreakdownChart');
        if (boardCtx) {
            if (window.__nsyncCharts.boardBreakdown) {
                window.__nsyncCharts.boardBreakdown.destroy();
            }

            const boardGradient = boardCtx.getContext('2d').createLinearGradient(0, 0, 0, 260);
            boardGradient.addColorStop(0, themePalette.barStart);
            boardGradient.addColorStop(1, themePalette.barEnd);

            window.__nsyncCharts.boardBreakdown = new Chart(boardCtx, {
                type: 'bar',
                data: {
                    labels: @json($boardLabels),
                    datasets: [{
                        label: 'Tasks',
                        data: @json($boardData),
                        backgroundColor: boardGradient,
                        borderColor: themePalette.lineBorder,
                        borderWidth: 1,
                        borderRadius: 8,
                        borderSkipped: false
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false
                        }
                    },
                    scales: {
                        x: {
                            ticks: { maxRotation: 0, minRotation: 0 }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(148,163,184,0.18)' }
                        }
                    },
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    animation: {
                        duration: 900,
                        easing: 'easeOutQuart'
                    },
                }
            });
        }

        const memberCtx = document.getElementById('memberWorkloadChart');
        if (memberCtx) {
            if (window.__nsyncCharts.memberWorkload) {
                window.__nsyncCharts.memberWorkload.destroy();
            }

            const memberLabels = @json($memberWorkloadLabels);
            const memberData = @json($memberWorkloadData);
            const memberColors = buildPieGradientColors(memberLabels, themePalette.pieStart, themePalette.pieEnd);
            const totalMemberTasks = memberData.reduce((sum, value) => sum + Number(value || 0), 0);

            window.__nsyncCharts.memberWorkload = new Chart(memberCtx, {
                type: 'pie',
                data: {
                    labels: memberLabels,
                    datasets: [{
                        label: 'Member Workload',
                        data: memberData,
                        backgroundColor: memberColors,
                        borderColor: '#ffffff',
                        borderWidth: 2,
                        radius: '80%',
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: 0 },
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 10,
                                boxHeight: 10,
                                generateLabels: (chart) => {
                                    const defaultLabels = Chart.defaults.plugins.legend.labels.generateLabels(chart);
                                    return defaultLabels.map((item, idx) => {
                                        const value = Number(memberData[idx] || 0);
                                        const pct = totalMemberTasks > 0 ? ((value / totalMemberTasks) * 100) : 0;
                                        const memberName = String(memberLabels[idx] || `Member ${idx + 1}`);
                                        return {
                                            ...item,
                                            text: `${memberName} (${pct.toFixed(1)}%)`,
                                        };
                                    });
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const value = Number(context.raw || 0);
                                    const pct = totalMemberTasks > 0 ? ((value / totalMemberTasks) * 100) : 0;
                                    const memberName = String(memberLabels[context.dataIndex] || context.label || `Member ${context.dataIndex + 1}`);
                                    return `${memberName}: ${value} tasks (${pct.toFixed(1)}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 900,
                        easing: 'easeOutQuart'
                    },
                }
            });
        }

        window.addEventListener('tenant-theme-updated', () => {
            applyThemeToDashboardCharts();
        });
    </script>
@endpush





