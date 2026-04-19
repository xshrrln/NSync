<?php
use Livewire\Volt\Component;
use App\Models\Board;
use App\Models\Task;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

new class extends Component {
    public $name = '';
    public $editingBoardId = null;
    public $editingBoardName = '';
    public $boardSearch = '';
    public $boardSort = 'recent';

    /**
     * Computed property to fetch boards. 
     * Access in Blade using $this->boards
     */
    #[Computed]
    public function boards()
    {
        $tenant = app('currentTenant');
        if (! $tenant) {
            return collect();
        }

        $query = Board::query()
            ->withCount([
                'tasks',
                'stages',
                'tasks as overdue_tasks_count' => fn ($q) => $q->whereDate('due_date', '<', now()->toDateString()),
                'tasks as due_soon_tasks_count' => fn ($q) => $q->whereBetween('due_date', [now()->toDateString(), now()->addDays(3)->toDateString()]),
            ]);

        if (filled($this->boardSearch)) {
            $query->where('name', 'like', '%' . trim($this->boardSearch) . '%');
        }

        $sort = in_array($this->boardSort, ['recent', 'oldest', 'name', 'tasks'], true)
            ? $this->boardSort
            : 'recent';

        match ($sort) {
            'oldest' => $query->orderBy('created_at'),
            'name' => $query->orderBy('name'),
            'tasks' => $query->orderByDesc('tasks_count')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        return $query->get();
    }

    public function with()
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;
        $isFreePlan = strtolower($tenant?->plan ?? 'free') === 'free';
        $boards = $this->boards->map(fn (Board $board) => $this->hydrateBoardMetrics($board));

        return [
            'boards' => $boards,
            'canCreateBoards' => Gate::allows('create', Board::class),
            'tenant' => $tenant,
            'isFreePlan' => $isFreePlan,
            'isPaidPlan' => ! $isFreePlan,
            'boardSummary' => [
                'total' => $boards->count(),
                'tasks' => (int) $boards->sum(fn ($board) => (int) ($board->task_count_display ?? $board->tasks_count ?? 0)),
                'active' => (int) $boards->filter(fn ($board) => (int) ($board->task_count_display ?? $board->tasks_count ?? 0) > 0)->count(),
                'dueSoon' => (int) $boards->sum(fn ($board) => (int) ($board->due_soon_count_display ?? $board->due_soon_tasks_count ?? 0)),
                'overdue' => (int) $boards->sum(fn ($board) => (int) ($board->overdue_count_display ?? $board->overdue_tasks_count ?? 0)),
            ],
            'templateCategories' => collect($this->templateDefinitions())
                ->pluck('category')
                ->unique()
                ->values()
                ->all(),
            'templates' => $this->templateDefinitions(),
        ];
    }

    private function hydrateBoardMetrics(Board $board): Board
    {
        $tasksCount = (int) ($board->tasks_count ?? 0);
        $stagesCount = (int) ($board->stages_count ?? 0);
        $dueSoonCount = (int) ($board->due_soon_tasks_count ?? 0);
        $overdueCount = (int) ($board->overdue_tasks_count ?? 0);
        $isCapstone = $this->isCapstoneBoard($board);

        if ($isCapstone) {
            $capstoneTasks = $this->capstoneTasksFromBoard($board);
            $today = Carbon::today();
            $soon = $today->copy()->addDays(3);

            $tasksCount = count($capstoneTasks);
            $dueSoonCount = collect($capstoneTasks)->filter(function (array $task) use ($today, $soon) {
                $due = $this->safeDate($task['due_date'] ?? null);
                $progress = (int) ($task['progress'] ?? 0);
                return $due && $due->betweenIncluded($today, $soon) && $progress < 100;
            })->count();
            $overdueCount = collect($capstoneTasks)->filter(function (array $task) use ($today) {
                $due = $this->safeDate($task['due_date'] ?? null);
                $progress = (int) ($task['progress'] ?? 0);
                return $due && $due->lt($today) && $progress < 100;
            })->count();

            // Capstone table has fixed tracker columns, not kanban stages.
            $stagesCount = 7;
        }

        $board->setAttribute('is_capstone_board', $isCapstone);
        $board->setAttribute('task_count_display', $tasksCount);
        $board->setAttribute('stage_count_display', $stagesCount);
        $board->setAttribute('due_soon_count_display', $dueSoonCount);
        $board->setAttribute('overdue_count_display', $overdueCount);

        return $board;
    }

    private function isCapstoneBoard(Board $board): bool
    {
        $members = is_array($board->members) ? $board->members : [];
        if (isset($members['capstone_tasks']) && is_array($members['capstone_tasks'])) {
            return true;
        }

        $name = Str::lower((string) $board->name);
        $slug = Str::lower((string) $board->slug);

        return Str::contains($name, 'capstone')
            || Str::contains($name, 'gantt')
            || Str::contains($slug, 'capstone')
            || Str::contains($slug, 'gantt');
    }

    private function capstoneTasksFromBoard(Board $board): array
    {
        $members = is_array($board->members) ? $board->members : [];
        $tasks = $members['capstone_tasks'] ?? [];

        if (! is_array($tasks)) {
            return [];
        }

        return array_values(array_filter($tasks, fn ($task) => is_array($task)));
    }

    private function safeDate(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function ensureSubscriptionAccess(string $title = 'Subscription Required'): bool
    {
        $tenant = app()->bound('currentTenant') ? app('currentTenant') : null;

        if (! $tenant || ! $tenant->requiresSubscriptionRenewal()) {
            return true;
        }

        $this->dispatch('subscription-expired', title: $title, message: $tenant->subscriptionLockMessage());

        return false;
    }

    public function createBoard() {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!Gate::allows('create', Board::class)) {
            $this->addError('name', 'You are not allowed to create boards.');
            return;
        }

        $this->validate(['name' => 'required|min:3|max:100']);
        $tenant = app('currentTenant');

        if (!$tenant) {
            $this->addError('name', 'No active tenant workspace found.');
            return;
        }

        $tenantScopedId = $this->resolveTenantScopedId($tenant);

        $tenantUserId = $this->resolveTenantUserId($tenantScopedId);
        if (!$tenantUserId) {
            $this->addError('name', 'Unable to map your account to tenant workspace user.');
            return;
        }

        try {
            $boardName = trim($this->name) . ' ' . now()->format('M d');
            $board = Board::forceCreate([
                'tenant_id' => $tenantScopedId,
                'user_id' => $tenantUserId,
                'name' => $boardName,
                'slug' => str($boardName)->slug() . '-' . uniqid(),
            ]);
            $board->stages()->createMany([
                ['tenant_id' => $tenantScopedId, 'name' => 'Design', 'position' => 1],
                ['tenant_id' => $tenantScopedId, 'name' => 'To Do', 'position' => 2],
                ['tenant_id' => $tenantScopedId, 'name' => 'In Progress', 'position' => 3],
                ['tenant_id' => $tenantScopedId, 'name' => 'Testing', 'position' => 4],
                ['tenant_id' => $tenantScopedId, 'name' => 'Done', 'position' => 5],
            ]);
        } catch (\Throwable $e) {
            Log::error('Board creation failed.', [
                'tenant_id' => $tenantScopedId,
                'user_id' => $tenantUserId,
                'board_name' => $this->name,
                'error' => $e->getMessage(),
            ]);

            $this->addError('name', 'Unable to create board right now. Please try again.');
            return;
        }

        $this->name = '';
        $this->dispatch('board-created');
        $this->dispatch('notify', message: 'Board created successfully.', type: 'success');
    }

    public function createBoardFromTemplate(string $templateKey): void
    {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        if (!Gate::allows('create', Board::class)) {
            $this->dispatch('notify', message: 'You are not allowed to create boards.', type: 'error');
            return;
        }

        $tenant = app('currentTenant');
        if (!$tenant) {
            $this->dispatch('notify', message: 'No active tenant workspace found.', type: 'error');
            return;
        }

        $tenantScopedId = $this->resolveTenantScopedId($tenant);

        $tenantUserId = $this->resolveTenantUserId($tenantScopedId);
        if (!$tenantUserId) {
            $this->dispatch('notify', message: 'Unable to map your account to tenant workspace user.', type: 'error');
            return;
        }

        $template = collect($this->templateDefinitions())->firstWhere('key', $templateKey);
        if (!$template) {
            $this->dispatch('notify', message: 'Template not found.', type: 'error');
            return;
        }

        try {
            $boardName = $template['name'] . ' ' . now()->format('M d');
            $board = Board::forceCreate([
                'tenant_id' => $tenantScopedId,
                'user_id' => $tenantUserId,
                'name' => $boardName,
                'slug' => str($boardName)->slug() . '-' . uniqid(),
            ]);

            $stagePayload = collect($template['stages'])
                ->values()
                ->map(fn ($stage, $index) => [
                    'tenant_id' => $tenantScopedId,
                    'name' => $stage,
                    'position' => $index + 1,
                ])
                ->all();

            $board->stages()->createMany($stagePayload);

            if (!empty($template['tasks'])) {
                $stageMap = $board->stages()->pluck('id', 'name');

                foreach ($template['tasks'] as $taskData) {
                    $stageId = $stageMap[$taskData['stage']] ?? null;
                    if (!$stageId) {
                        continue;
                    }

                    Task::create([
                        'tenant_id' => $tenantScopedId,
                        'user_id' => $tenantUserId,
                        'board_id' => $board->id,
                        'stage_id' => $stageId,
                        'title' => $taskData['title'],
                        'description' => $taskData['description'] ?? null,
                        'due_date' => isset($taskData['due_in_days']) ? now()->addDays($taskData['due_in_days'])->toDateString() : null,
                        'position' => 0,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('Board template creation failed.', [
                'tenant_id' => $tenantScopedId,
                'user_id' => $tenantUserId,
                'template_key' => $templateKey,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('notify', message: 'Unable to create this template right now.', type: 'error');
            return;
        }

        $this->dispatch('board-created');
        $this->dispatch('template-created');
        $this->dispatch('notify', message: "Board created from {$template['name']} template.", type: 'success');
    }

    private function resolveTenantScopedId($tenant): int
    {
        $tenantId = (int) $tenant->id;

        if (!Schema::connection('tenant')->hasTable('tenants')) {
            return $tenantId;
        }

        $tenantTable = DB::connection('tenant')->table('tenants');
        if ($tenantTable->where('id', $tenantId)->exists()) {
            return $tenantId;
        }

        $payload = [];
        $now = now();

        if (Schema::connection('tenant')->hasColumn('tenants', 'id')) {
            $payload['id'] = $tenantId;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'organization')) {
            $payload['organization'] = $tenant->organization ?: ($tenant->name ?: 'Organization ' . $tenantId);
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'name')) {
            $payload['name'] = $tenant->name ?: $tenant->organization ?: ('Tenant ' . $tenantId);
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'address')) {
            $payload['address'] = $tenant->address;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'domain')) {
            $payload['domain'] = $tenant->domain;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'tenant_admin')) {
            $payload['tenant_admin'] = $tenant->tenant_admin ?: ($tenant->name ?: 'Tenant Admin');
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'tenant_admin_email')) {
            $payload['tenant_admin_email'] = $tenant->tenant_admin_email ?: (auth()->user()?->email ?: 'tenant@example.com');
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'plan')) {
            $payload['plan'] = $tenant->plan ?: 'free';
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'start_date')) {
            $payload['start_date'] = $tenant->start_date;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'due_date')) {
            $payload['due_date'] = $tenant->due_date;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'database')) {
            $payload['database'] = $tenant->database ?: null;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'theme')) {
            $payload['theme'] = is_array($tenant->theme) ? json_encode($tenant->theme) : $tenant->theme;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'actions')) {
            $payload['actions'] = is_array($tenant->actions) ? json_encode($tenant->actions) : $tenant->actions;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'billing_data')) {
            $payload['billing_data'] = is_array($tenant->billing_data) ? json_encode($tenant->billing_data) : $tenant->billing_data;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'status')) {
            $payload['status'] = $tenant->status ?: 'active';
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'created_at')) {
            $payload['created_at'] = $now;
        }
        if (Schema::connection('tenant')->hasColumn('tenants', 'updated_at')) {
            $payload['updated_at'] = $now;
        }

        $matchedTenantId = null;

        if (Schema::connection('tenant')->hasColumn('tenants', 'domain') && !empty($tenant->domain)) {
            $matchedTenantId = (int) (DB::connection('tenant')
                ->table('tenants')
                ->where('domain', $tenant->domain)
                ->value('id') ?: 0);
        }

        if (!$matchedTenantId && Schema::connection('tenant')->hasColumn('tenants', 'database') && !empty($tenant->database)) {
            $matchedTenantId = (int) (DB::connection('tenant')
                ->table('tenants')
                ->where('database', $tenant->database)
                ->value('id') ?: 0);
        }

        if (!$matchedTenantId && Schema::connection('tenant')->hasColumn('tenants', 'tenant_admin_email') && !empty($tenant->tenant_admin_email)) {
            $matchedTenantId = (int) (DB::connection('tenant')
                ->table('tenants')
                ->where('tenant_admin_email', $tenant->tenant_admin_email)
                ->value('id') ?: 0);
        }

        if ($matchedTenantId) {
            $updatePayload = $payload;
            unset($updatePayload['id'], $updatePayload['created_at']);

            try {
                if ($matchedTenantId !== $tenantId && !$tenantTable->where('id', $tenantId)->exists()) {
                    $tenantTable->where('id', $matchedTenantId)->update(array_merge(['id' => $tenantId], $updatePayload));
                    return $tenantId;
                }

                $tenantTable->where('id', $matchedTenantId)->update($updatePayload);
                return $matchedTenantId;
            } catch (\Throwable $e) {
                Log::warning('Tenant row remap in tenant DB failed.', [
                    'tenant_id' => $tenantId,
                    'matched_tenant_id' => $matchedTenantId,
                    'domain' => $tenant->domain,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $tenantTable->insert($payload);
            return $tenantId;
        } catch (\Throwable $e) {
            Log::warning('Tenant row sync in tenant DB failed.', [
                'tenant_id' => $tenantId,
                'domain' => $tenant->domain,
                'error' => $e->getMessage(),
            ]);

            if ($tenantTable->where('id', $tenantId)->exists()) {
                return $tenantId;
            }

            // Last-resort fallback: use first tenant row in tenant DB.
            $firstTenantId = DB::connection('tenant')->table('tenants')->orderBy('id')->value('id');
            if ($firstTenantId) {
                return (int) $firstTenantId;
            }

            return $tenantId;
        }
    }

    private function resolveTenantUserId(int $tenantId): ?int
    {
        $authUser = auth()->user();
        if (!$authUser) {
            return null;
        }

        $users = DB::connection('tenant')->table('users');

        $existingId = $users
            ->where('email', $authUser->email)
            ->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        // Fallback for legacy data where the same numeric user id already exists.
        $legacyId = $users->where('id', $authUser->id)->value('id');
        if ($legacyId) {
            return (int) $legacyId;
        }

        $now = now();
        $payload = [
            'name' => $authUser->name,
            'email' => $authUser->email,
            'password' => $authUser->password,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::connection('tenant')->hasColumn('users', 'email_verified_at')) {
            $payload['email_verified_at'] = $authUser->email_verified_at ?: $now;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'remember_token')) {
            $payload['remember_token'] = $authUser->remember_token;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'tenant_id')) {
            $payload['tenant_id'] = $tenantId;
        }

        if (Schema::connection('tenant')->hasColumn('users', 'role')) {
            $payload['role'] = $authUser->hasRole('Team Supervisor') ? 'supervisor' : 'member';
        }

        try {
            $insertedId = $users->insertGetId($payload);

            return $insertedId ? (int) $insertedId : null;
        } catch (\Throwable $e) {
            // Concurrent requests may insert the same email first; resolve gracefully.
            Log::warning('Tenant user auto-sync failed during board creation.', [
                'email' => $authUser->email,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            $retryId = DB::connection('tenant')
                ->table('users')
                ->where('email', $authUser->email)
                ->value('id');

            if ($retryId) {
                return (int) $retryId;
            }

            // Last-resort fallback to keep board creation functional.
            $anyUserId = DB::connection('tenant')->table('users')->orderBy('id')->value('id');
            return $anyUserId ? (int) $anyUserId : null;
        }
    }

    private function templateDefinitions(): array
    {
        return [
            [
                'key' => 'gantt-chart',
                'name' => 'Capstone Task Tracker',
                'category' => 'Academic & Project',
                'description' => 'Structured task table with inline editing, auto duration, and overall progress.',
                'stages' => ['Tasks'],
                'tasks' => [],
            ],
            [
                'key' => 'sprint-planning',
                'name' => 'Sprint Planning',
                'category' => 'Product & Project',
                'description' => 'Run agile sprints with backlog, active work, review, and done.',
                'stages' => ['Backlog', 'Sprint To Do', 'In Progress', 'Code Review', 'Done'],
                'tasks' => [],
            ],
            [
                'key' => 'marketing-campaign',
                'name' => 'Marketing Campaign',
                'category' => 'Marketing & Content',
                'description' => 'Plan campaign assets from ideas to scheduled and published.',
                'stages' => ['Ideas', 'Drafting', 'Design', 'Scheduled', 'Published'],
                'tasks' => [],
            ],
            [
                'key' => 'content-calendar',
                'name' => 'Content Calendar',
                'category' => 'Marketing & Content',
                'description' => 'Track weekly content production and publishing flow.',
                'stages' => ['Backlog', 'Writing', 'Editing', 'Ready', 'Published'],
                'tasks' => [],
            ],
            [
                'key' => 'sales-crm',
                'name' => 'Sales CRM',
                'category' => 'Operations',
                'description' => 'Move leads through discovery, proposal, negotiation, and close.',
                'stages' => ['Lead', 'Qualified', 'Proposal', 'Negotiation', 'Won/Lost'],
                'tasks' => [],
            ],
            [
                'key' => 'support-queue',
                'name' => 'Support Queue',
                'category' => 'Operations',
                'description' => 'Organize support tickets from intake to resolution.',
                'stages' => ['New', 'Investigating', 'Waiting on User', 'Resolved'],
                'tasks' => [],
            ],
        ];
    }

    public function editBoard($boardId) {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        $board = Board::where('id', $boardId)->firstOrFail();
        if (Gate::denies('update', $board)) {
            abort(403);
        }
        $this->editingBoardId = $boardId;
        $this->editingBoardName = $board->name;
    }

    public function updateBoard() {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        $this->validate(['editingBoardName' => 'required|min:3|max:100']);
        $board = Board::where('id', $this->editingBoardId)->firstOrFail();
        if (Gate::denies('update', $board)) {
            abort(403);
        }
        $board->update(['name' => $this->editingBoardName]);
        $this->editingBoardId = null;
    }

    public function deleteBoard($boardId) {
        if (! $this->ensureSubscriptionAccess()) {
            return;
        }

        $board = Board::where('id', $boardId)->firstOrFail();
        if (Gate::denies('delete', $board)) {
            abort(403);
        }
        $board->delete();
    }
}; ?>

<div class="bg-white min-h-screen" x-data="{ open: false, templateView: false, templateCategory: 'all', templateSearch: '', boardView: 'grid' }" @board-created.window="open = false" @template-created.window="templateView = false">
    
    <div class="bg-white shadow-sm border-b sticky top-0 z-30">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex items-center justify-between gap-4 py-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold mb-0" style="color: color-mix(in srgb, var(--tenant-primary) 88%, black 12%);">Boards</h1>
                    <p class="text-gray-600 mb-0">Manage and organize your project boards</p>
                </div>

                @if($canCreateBoards)
                <div class="flex items-center gap-3">
                    <button @click="templateView = true" class="px-5 py-2 bg-white border border-gray-300 text-gray-800 font-medium rounded-lg hover:border-nsync-green-300 hover:text-nsync-green-700 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path d="M4 7a2 2 0 012-2h3l2 2h7a2 2 0 012 2v8a2 2 0 01-2 2H6a2 2 0 01-2-2V7z"/>
                        </svg>
                        Browse Templates
                    </button>
                    <button @click="open = true" class="px-6 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path d="M12 6v6m0 0v6m0-6h6m0 0h6"/>
                        </svg>
                        Create Board
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-6 py-8 relative" x-show="!templateView">
        <div wire:loading wire:target="deleteBoard, updateBoard, createBoard, createBoardFromTemplate" class="absolute inset-0 bg-white/40 z-10 backdrop-blur-[1px] rounded-xl"></div>

        @if($isPaidPlan)
            <div class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-nsync-green-700">Board Workspace</p>
                    <h2 class="mt-1 text-xl font-bold text-slate-900">Advanced Board Hub</h2>
                    <p class="mt-1 text-sm text-slate-600">Find boards faster, sort by priority, and monitor due-task pressure at a glance.</p>
                </div>

                <div class="px-5 py-4">
                    <div class="flex w-full flex-nowrap items-end gap-4">
                        <div class="min-w-0 flex-1">
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search Boards</label>
                            <input wire:model.live.debounce.250ms="boardSearch" type="text" placeholder="Search by board name..." class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 placeholder:text-slate-400 focus:border-nsync-green-500 focus:outline-none focus:ring-2 focus:ring-nsync-green-100">
                        </div>
                        <div class="flex flex-shrink-0 items-end gap-4">
                            <div class="w-56">
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Sort By</label>
                                <select wire:model.live="boardSort" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-nsync-green-500 focus:outline-none focus:ring-2 focus:ring-nsync-green-100">
                                    <option value="recent">Most Recent</option>
                                    <option value="oldest">Oldest</option>
                                    <option value="name">Name A-Z</option>
                                    <option value="tasks">Most Tasks</option>
                                </select>
                            </div>
                            <div class="w-56 flex items-end">
                                <button @click="boardView = boardView === 'grid' ? 'list' : 'grid'" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:border-nsync-green-300 hover:text-nsync-green-700">
                                    <span x-text="boardView === 'grid' ? 'Switch to List' : 'Switch to Grid'"></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Boards</p>
                            <p class="text-lg font-black text-slate-900">{{ number_format($boardSummary['total']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Tasks</p>
                            <p class="text-lg font-black text-slate-900">{{ number_format($boardSummary['tasks']) }}</p>
                        </div>
                        <div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500">Active</p>
                            <p class="text-lg font-black text-slate-900">{{ number_format($boardSummary['active']) }}</p>
                        </div>
                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-amber-700">Due Soon</p>
                            <p class="text-lg font-black text-amber-900">{{ number_format($boardSummary['dueSoon']) }}</p>
                        </div>
                        <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2">
                            <p class="text-[11px] uppercase tracking-wide text-rose-700">Overdue</p>
                            <p class="text-lg font-black text-rose-900">{{ number_format($boardSummary['overdue']) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($boards->count() > 0)
            <div class="grid gap-6" :class="boardView === 'list' ? 'grid-cols-1' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'">
                @foreach($boards as $board)
                    <div class="group" wire:key="board-{{ $board->id }}">
                        
                        @if($editingBoardId === $board->id)
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                                <input type="text" wire:model="editingBoardName" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 text-sm font-bold mb-4">
                                <div class="flex gap-2">
                                    <button wire:click="updateBoard" class="flex-1 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">Save</button>
                                    <button wire:click="$set('editingBoardId', null)" class="flex-1 px-4 py-2 bg-gray-200 text-gray-900 text-sm font-medium rounded-lg hover:bg-gray-300">Cancel</button>
                                </div>
                            </div>
                        @else
                            <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-lg transition-all h-full relative group">
                                <a href="{{ route('boards.show', $board->slug) }}" class="block p-6">
                                    <div class="mb-3 flex items-start justify-between gap-3">
                                        <div>
                                            <h3 class="text-lg font-bold text-gray-900 leading-tight">{{ $board->name }}</h3>
                                            <p class="mt-1 text-xs text-gray-500">{{ $board->is_capstone_board ? 'Capstone Tracker Board' : 'Kanban Board' }}</p>
                                        </div>
                                        <div class="bg-nsync-green-100 rounded-full flex items-center justify-center w-8 h-8 shrink-0">
                                            <svg class="text-nsync-green-600 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2"/>
                                            </svg>
                                        </div>
                                    </div>

                                    <p class="text-gray-600 text-sm mb-0 flex items-center gap-1">
                                        <span class="inline-block w-2 h-2 bg-green-500 rounded-full"></span>
                                        Active Board
                                    </p>

                                    @if($isPaidPlan)
                                        <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                                            <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                                                <p class="text-slate-500">Tasks</p>
                                                <p class="font-bold text-slate-800">{{ number_format($board->task_count_display ?? 0) }}</p>
                                            </div>
                                            <div class="rounded-md border border-slate-200 bg-slate-50 px-2 py-1.5">
                                                <p class="text-slate-500">{{ $board->is_capstone_board ? 'Tracker Columns' : 'Columns' }}</p>
                                                <p class="font-bold text-slate-800">{{ number_format($board->stage_count_display ?? 0) }}</p>
                                            </div>
                                            <div class="rounded-md border border-amber-200 bg-amber-50 px-2 py-1.5">
                                                <p class="text-amber-700">Due Soon</p>
                                                <p class="font-bold text-amber-900">{{ number_format($board->due_soon_count_display ?? 0) }}</p>
                                            </div>
                                            <div class="rounded-md border border-rose-200 bg-rose-50 px-2 py-1.5">
                                                <p class="text-rose-700">Overdue</p>
                                                <p class="font-bold text-rose-900">{{ number_format($board->overdue_count_display ?? 0) }}</p>
                                            </div>
                                        </div>
                                    @endif

                                    <div class="mt-4 pt-3 border-t border-slate-100">
                                        <span class="inline-flex items-center gap-1 text-xs font-semibold text-nsync-green-700">
                                            Open Board
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </span>
                                    </div>
                                </a>

                                @if(auth()->user()->hasRole('Team Supervisor'))
                                <div class="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity bg-white/90 p-1 rounded-md shadow-sm">
                                    <button wire:click="editBoard({{ $board->id }})" class="p-1 text-gray-400 hover:text-blue-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </button>
                                    <button wire:click="deleteBoard({{ $board->id }})" wire:confirm="Delete this board?" class="p-1 text-gray-400 hover:text-red-600">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-4v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white border-2 border-dashed border-gray-300 rounded-lg text-center py-12">
                <h3 class="text-lg font-bold text-gray-900 mb-2">No boards yet</h3>
                <p class="text-gray-600 mb-6">{{ $isPaidPlan ? 'Create a high-performance workspace with templates and board insights.' : 'Create your first board to start organizing projects.' }}</p>
                @if($canCreateBoards)
                <div class="flex items-center justify-center gap-3">
                    <button @click="templateView = true" class="inline-flex items-center px-6 py-3 bg-white border border-gray-300 text-gray-800 font-medium rounded-lg hover:border-nsync-green-300 hover:text-nsync-green-700 transition">
                        Browse Templates
                    </button>
                    <button @click="open = true" class="inline-flex items-center px-6 py-3 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700 transition">
                        Create Your First Board
                    </button>
                </div>
                @endif
            </div>
        @endif
    </div>

    @if($canCreateBoards)
    <div x-show="templateView" x-cloak class="max-w-7xl mx-auto relative" style="padding: 6px 8px;">
        <div class="bg-white overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-600 to-green-500 text-white rounded-xl" style="padding: 14px 0;">
                <div class="flex items-start justify-between gap-4" style="padding: 0 12px;">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-100">Board Templates</p>
                        <h2 class="text-2xl font-bold mt-1">Start Fast With Ready-Made Workflows</h2>
                        <p class="text-sm text-emerald-50 mt-2">Pick a category, preview the board structure, then create instantly.</p>
                    </div>
                    <button @click="templateView = false" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-white/20 hover:bg-white/30 text-sm font-medium">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        Back
                    </button>
                </div>
            </div>

            <div class="border-b border-gray-100 bg-white" style="padding: 10px 0;">
                <div class="flex flex-col md:flex-row md:items-center gap-3 px-0">
                    <div class="relative flex-1">
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        <input type="text"
                               x-model="templateSearch"
                               placeholder="Search templates (e.g. gantt, sprint, marketing)..."
                               class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-sm">
                    </div>
                    <button @click="templateCategory = 'all'; templateSearch = ''" class="px-4 py-2.5 text-sm font-medium rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50">
                        Reset
                    </button>
                </div>
            </div>

            <div class="border-b border-gray-100 bg-gray-50/70" style="padding: 8px 0;">
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    <button @click="templateCategory = 'all'" :class="templateCategory === 'all' ? 'bg-nsync-green-600 text-white border-nsync-green-600' : 'bg-white text-gray-700 border-gray-300 hover:border-gray-400'" class="whitespace-nowrap px-3 py-1.5 rounded-full border text-sm font-medium transition">
                        All
                    </button>
                    @foreach($templateCategories as $category)
                        @php $categoryKey = strtolower($category); @endphp
                        <button @click="templateCategory = @js($categoryKey)" :class="templateCategory === @js($categoryKey) ? 'bg-nsync-green-600 text-white border-nsync-green-600' : 'bg-white text-gray-700 border-gray-300 hover:border-gray-400'" class="whitespace-nowrap px-3 py-1.5 rounded-full border text-sm font-medium transition">
                            {{ $category }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="bg-white" style="padding: 10px 6px 8px 6px;">
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3" style="gap: 24px;">
                    @foreach($templates as $template)
                        @php
                            $templateCategory = strtolower($template['category']);
                            $templateSearchText = strtolower($template['name'] . ' ' . $template['description'] . ' ' . $template['category']);
                        @endphp
                        <div
                            x-show="(templateCategory === 'all' || templateCategory === @js($templateCategory)) && @js($templateSearchText).includes(templateSearch.toLowerCase())"
                            class="rounded-2xl border border-gray-200 bg-white overflow-hidden hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
                        >
                            <div class="h-24 px-4 py-3 bg-gradient-to-br from-gray-100 to-gray-50 border-b border-gray-200">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="inline-flex px-2 py-1 text-[11px] rounded-full bg-white text-gray-700 font-semibold border border-gray-200">{{ $template['category'] }}</span>
                                    <span class="text-[11px] text-gray-500 font-medium">{{ count($template['stages']) }} stages</span>
                                </div>
                                <div class="flex gap-1.5">
                                    @foreach(collect($template['stages'])->take(4) as $stageName)
                                        <span class="h-2.5 flex-1 rounded-full bg-emerald-200/80"></span>
                                    @endforeach
                                </div>
                            </div>

                            <div class="p-4">
                                <h3 class="text-base font-bold text-gray-900">{{ $template['name'] }}</h3>
                                <p class="text-sm text-gray-600 mt-2 min-h-[3rem]">{{ $template['description'] }}</p>

                                <div class="mt-3 flex flex-wrap gap-1.5">
                                    @foreach(collect($template['stages'])->take(3) as $stageName)
                                        <span class="text-[11px] px-2 py-1 rounded-md bg-gray-100 text-gray-700">{{ $stageName }}</span>
                                    @endforeach
                                    @if(count($template['stages']) > 3)
                                        <span class="text-[11px] px-2 py-1 rounded-md bg-gray-100 text-gray-600">+{{ count($template['stages']) - 3 }} more</span>
                                    @endif
                                </div>

                                <button wire:click="createBoardFromTemplate('{{ $template['key'] }}')" class="mt-4 w-full px-4 py-2.5 bg-nsync-green-600 text-white text-sm font-semibold rounded-xl hover:bg-nsync-green-700 transition">
                                    Use Template
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

    @if($canCreateBoards)
    <div x-show="open" 
         class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
         x-transition.opacity
         x-cloak>
        <div @click.away="open = false" class="bg-white w-full max-w-md rounded-xl shadow-2xl border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-50 flex justify-between items-center bg-gray-50/50">
                <h2 class="text-lg font-bold text-gray-900">Create New Board</h2>
                <button @click="open = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="mb-6">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Board Name</label>
                    <input type="text" wire:model="name" wire:keydown.enter="createBoard" x-init="$el.focus()"
                           placeholder="Enter board name..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-nsync-green-500 focus:border-transparent text-sm">
                </div>
                <div class="flex gap-3">
                    <button @click="open = false" class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-lg">Cancel</button>
                    <button wire:click="createBoard" class="flex-1 px-4 py-2 bg-nsync-green-600 text-white font-medium rounded-lg hover:bg-nsync-green-700">Create Board</button>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>




